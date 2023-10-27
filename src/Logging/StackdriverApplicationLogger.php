<?php


namespace Ingenerator\PHPUtils\Logging;


use Ingenerator\PHPUtils\ArrayHelpers\AssociativeArrayUtils;
use Ingenerator\PHPUtils\Monitoring\MetricId;
use Ingenerator\PHPUtils\Monitoring\MetricsAgent;
use Ingenerator\PHPUtils\Object\InitialisableSingletonTrait;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use RuntimeException;
use Throwable;
use function array_pad;
use function debug_backtrace;
use function file_put_contents;
use function get_class;
use function http_response_code;
use function is_callable;
use function json_encode;
use function mb_strcut;
use function microtime;
use function sprintf;
use function strlen;
use function strtoupper;
use function substr;


/**
 * Log destination that formats logs as JSON lines for forwarding into Stackdriver Logging, with relevant metadata
 *
 * Also provides a request logger that can be called on system shutdown to log the HTTP request itself into Stackdriver
 * again with appropriate application-level metadata.
 *
 * Exceptions will be formatted and (by default) tagged to be reported to Stackdriver Errors - see `->log()` for details
 */
class StackdriverApplicationLogger extends AbstractLogger
{
    use InitialisableSingletonTrait;

    const PROP_EXCEPTION                = 'exception';
    const PROP_INGEN_TYPE               = '@ingenType';
    const PROP_REPORT_STACKDRIVER_ERROR = '_report_stackdriver_error';
    const PROP_SOURCE_LOCATION          = 'logging.googleapis.com/sourceLocation';
    const TYPE_STACKDRIVER_ERROR        = 'type.googleapis.com/google.devtools.clouderrorreporting.v1beta1.ReportedErrorEvent';

    /**
     * @var ExternalCallSiteFinder
     */
    protected $call_site_finder;

    /**
     * @var string
     */
    protected $log_destination;

    /**
     * @var array
     */
    protected $metadata;

    /**
     * @var array
     */
    protected               $metadata_sources;

    protected ?MetricsAgent $metrics_agent = NULL;

    protected string        $metric_name   = 'application-logs';

    /**
     * Create an instance that also reports a metric counter on each log entry.
     *
     * @param MetricsAgent                       $agent
     * @param string                             $metric_name
     * @param string                             $log_destination
     * @param array|callable|LogMetadataProvider ...$metadata_sources
     *
     * @return StackdriverApplicationLogger
     * @see __construct for the usage of the log_destination and metadata_sources arguments
     *
     */
    public static function withMetrics(
        MetricsAgent $agent,
        string       $metric_name,
        string       $log_destination,
                     ...$metadata_sources
    ): StackdriverApplicationLogger {
        $i                = new static($log_destination, ...$metadata_sources);
        $i->metrics_agent = $agent;
        $i->metric_name   = $metric_name;

        return $i;
    }

    /**
     * Create an instance of the logger and provide metadata
     *
     * The class takes an arbitrary number of metadata sources, each of which can be:
     *
     *   * an associative array of static metadata values such as those provided by DefaultLogMetadata::{type}
     *   * a callable that returns an array of metadata
     *   * an instance of LogMetadataProvider
     *
     * Functions and LogMetadataProvider instances will be called lazily when the first log entry is written (and only
     * once for a given request).
     *
     * It is *vital* that log metadata sources provided here cannot throw during construction as this will prevent the
     * creation of the log destination. Anything with potential to fail should be provided as a function /
     * LogMetadataProvider as the logger will catch and report exceptions thrown during execution of each lazy provider.
     *
     * Generally your application would create and use a singleton instance of this class, for example:
     *
     *   <?php
     *   // logger.php - you can require this from e.g. your bootstrap immediately after registering the composer autoloader
     *   // Alternatively for absolute safety you could hardcode all the `require_once` class definitions to this file
     *   // (with appropriate integration test to ensure you have loaded all the files needed if composer is not
     *   // registered).
     *   if ( ! StackdriverApplicationLogger::isInitialised()) {
     *       define(REQUEST_START_TIME, microtime(TRUE)); // This would actually be very early in your app index.php
     *       StackdriverApplicationLogger::initialise(
     *         function () {
     *           $logger = new StackdriverApplicationLogger(
     *               'php://stdout',
     *                DefaultLogMetadata::httpContext($_SERVER),
     *                new FrameworkLogMetadataProvider,
     *                function () { return ['context' => ['custom' => 'stuff']]},
     *                // etc
     *           );
     *
     *           // Optional - if you want to report stats on the number of log entries logged
     *           $logger->reportLogCounts(new StatsiteMetricsAgent, 'app-log-entries');
     *
     *           if (PHP_SAPI !== 'cli') {
     *             register_shutdown_function(
     *               function () use ($logger) {
     *                 $logger->logRequest($_SERVER, REQUEST_START_TIME);
     *               }
     *             )
     *           }
     *
     *           return $logger;
     *         }
     *       );
     *   }
     *
     *   return StackdriverApplicationLogger::instance();
     *
     * @param string                             $log_destination     A PHP file / stream destination to append, commonly php://stdout
     * @param array|callable|LogMetadataProvider ...$metadata_sources Sources of metadata to merge together
     */
    public function __construct(
        string $log_destination,
               ...$metadata_sources
    ) {
        $this->metadata_sources = array_pad($metadata_sources, 2, []);
        $this->log_destination  = $log_destination;
        $this->call_site_finder = new ExternalCallSiteFinder;
    }

    /**
     * Logs the message
     *
     * A small number of `context` keys will be 'hoisted' to override top-level log entry values (see the start
     * of the method below). All others will be presented in a `custom_context` key within the jsonPayload to avoid the
     * risk of callers overriding application level metadata or core message components.
     *
     * If you provide any `Throwable` instance in the `exception` key this will be formatted as a JSON structure with
     * the class, file, line, message and trace. The structure will recursively include any 'previous' exceptions
     * referenced by the object passed in. The trace string follows the standard PHP format, but with all function
     * arguments sanitised to prevent leakage of sensitive values (config / user input) in the log entry.
     *
     * Additionally, if you provide an `exception` key the logger will by default format the entry to be reported to
     * Stackdriver Error Reporting. To disable this (e.g. if you wish to log details of an 'expected' exception without
     * reporting an error) you can override this with a flag:
     *
     *    catch (GuzzleException $e) {
     *      $logger->warning(
     *        'API request failed, doing fallback thing',
     *        ['exception' => $e, StackdriverApplicationLogger::PROP_REPORT_STACKDRIVER_ERROR => FALSE]
     *      );
     *    }
     *
     * By default the log entry will report the source location as the first external call to any PSR\Log method.
     * If your application calls this logger through some other proxy / bridge class then your proxy should provide
     * a custom value for `StackdriverApplicationLogger::PROP_SOURCE_LOCATION`. The `ExternalCallSiteFinder` may be
     * useful.
     *
     * Note that the sourceLocation should be the location of the code that triggered the logging call (e.g. the error
     * handler / exception catch block). Where an exception is provided, the exception data structure will contain the
     * details of the location where the exception itself was thrown.
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     */
    public function log($level, $message, array $context = []): void
    {
        // These are the only `context` keys that will be used as top-level properties. Anything else is moved to a
        // `custom_context` key to prevent it overwriting internal metadata and low-level log entry properties e.g.
        // the message field.
        $context_overrides = AssociativeArrayUtils::popKeys(
            $context,
            [
                self::PROP_INGEN_TYPE,
                self::PROP_SOURCE_LOCATION,
                self::PROP_EXCEPTION,
                self::PROP_REPORT_STACKDRIVER_ERROR,
            ]
        );

        $log_entry = AssociativeArrayUtils::deepMerge(
            [
                'severity'            => strtoupper($level),
                'message'             => $message,
                self::PROP_INGEN_TYPE => 'app',
            ],
            $this->getMetadata(),
            $context_overrides
        );

        if ( ! isset($log_entry[self::PROP_SOURCE_LOCATION])) {
            // Find source location here rather than in the initial array merge to avoid having to make this backtrace
            // call if it's already been provided / overridden by the caller.
            $log_entry[self::PROP_SOURCE_LOCATION] = $this->call_site_finder->findExternalCall(
                debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5),
                [static::class]
            );
        }

        if ( ! empty($context)) {
            $log_entry['custom_context'] = $context;
        }

        if (($log_entry[self::PROP_EXCEPTION] ?? NULL) instanceof Throwable) {
            // Convert exceptions to a nested data format with previous chain and log-safe trace
            $log_entry[self::PROP_EXCEPTION] = $this->formatException($log_entry[self::PROP_EXCEPTION]);
            if ( ! isset($log_entry[self::PROP_REPORT_STACKDRIVER_ERROR])) {
                $log_entry[self::PROP_REPORT_STACKDRIVER_ERROR] = TRUE;
            }
        }

        if ($log_entry[self::PROP_REPORT_STACKDRIVER_ERROR] ?? FALSE) {
            $log_entry = $this->addStackdriverErrorReportingData($log_entry);
        }

        $this->writeLog($log_entry);

        if ($this->metrics_agent) {
            // Report the log entry metric if they've configured an agent to collect it
            $this->metrics_agent->incrementCounterByOne(MetricId::nameAndSource($this->metric_name, $level));
        }
    }

    /**
     * Lazily compiles all provided metadata
     *
     * @return array
     */
    protected function getMetadata(): array
    {
        if ($this->metadata !== NULL) {
            return $this->metadata;
        }

        $metadata    = [];
        $meta_errors = [];
        foreach ($this->metadata_sources as $index => $source) {
            try {
                if ($source instanceof LogMetadataProvider) {
                    $source = $source->getMetadata();
                } else if (is_callable($source)) {
                    $source = $source();
                }
                $metadata = AssociativeArrayUtils::deepMerge($metadata, $source);
            } catch (Throwable $e) {
                // @todo: include name/type of metadata source here (need a string helper to get the type name?)
                $meta_errors[] = ['idx' => $index, 'throwable' => $e];
            }
        }

        // Clean up any references to the sources, they're no longer needed
        $this->metadata_sources = NULL;

        $this->metadata = $metadata;

        foreach ($meta_errors as $err) {
            $e = $err['throwable'];
            /** @var Throwable $e */
            $this->alert(
                sprintf('Invalid log metadata source#%s [%s] %s', $err['idx'], get_class($e), $e->getMessage()),
                ['exception' => $e]
            );
        }

        return $this->metadata;
    }

    protected function formatException(Throwable $e): array
    {
        $result = [
            'class' => get_class($e),
            'msg'   => $e->getMessage(),
            'code'  => $e->getCode(),
            'file'  => $e->getFile(),
            'line'  => $e->getLine(),
            'trace' => $this->getExceptionTraceAsStringWithoutArgs($e->getTrace()),
        ];

        if ($previous = $e->getPrevious()) {
            $result['previous'] = $this->formatException($previous);
        }

        return $result;
    }

    /**
     * Get the exception call stack without any function arguments
     *
     * All of the out-of-the-box PHP exception traces include argument values for the called functions.
     * This may leak sensitive data (either user input or system values) that should not be present in
     * the logs unintentionally.
     *
     * This method renders a string trace that follows the format of the standard trace (so it can be
     * parsed by tools that are looking for that) but without including any function argument details.
     *
     * @param array $trace_stack
     *
     * @return string
     */
    protected function getExceptionTraceAsStringWithoutArgs(array $trace_stack): string
    {
        // @todo: An alternative approach would be to just look for and remove string values but keep other args?
        $trace_lines = [];
        foreach ($trace_stack as $index => $trace) {
            $trace_lines[] = sprintf(
                '#%d %s: %s%s%s()',
                $index,
                isset($trace['file']) ? $trace['file'].'('.$trace['line'].')' : '[internal function]',
                $trace['class'] ?? '',
                $trace['type'] ?? '',
                $trace['function']
            );
        }
        $trace_lines[] = '#'.count($trace_stack).' {main}';

        return implode("\n", $trace_lines);
    }

    protected function addStackdriverErrorReportingData(array $log_entry): array
    {
        $log_entry['@type'] = self::TYPE_STACKDRIVER_ERROR;

        // Thanks @google for these having different keys in context.reportLocation rather than using sourceLocation
        $source_location                        = $log_entry[self::PROP_SOURCE_LOCATION];
        $log_entry['context']['reportLocation'] = [
            'filePath'     => $source_location['file'] ?? NULL,
            'lineNumber'   => $source_location['line'] ?? NULL,
            'functionName' => $source_location['function'] ?? NULL,
        ];

        // If this was logged from an exception, add the exception info in Error Reporting's required format but with
        // our safe trace string.
        $exception = $log_entry[self::PROP_EXCEPTION] ?? NULL;
        if (is_array($exception) and isset($exception['class'])) {
            $log_entry['stack_trace'] = sprintf(
                "PHP Warning: %s: %s in %s:%s\nStack trace:\n%s",
                $exception['class'],
                $exception['msg'],
                $exception['file'],
                $exception['line'],
                $exception['trace']
            );
        }
        unset($log_entry[self::PROP_REPORT_STACKDRIVER_ERROR]);

        return $log_entry;
    }

    protected function writeLog(array $log_entry): void
    {
        try {
            $success = file_put_contents(
                $this->log_destination,
                json_encode($log_entry)."\n",
                FILE_APPEND
            );

            if ($success === FALSE) {
                throw new RuntimeException('Unknown write failure');
            }
        } catch (Throwable $e) {
            throw new LoggingFailureException($this->log_destination, $e);
        }
    }

    /**
     * Logs the http request as a standalone log entry - usually registered as a shutdown func
     *
     * The advantage of this over e.g. apache logs is that the log entry can contain application-specific metadata
     * such as the logged-in user, matched module/action/controller, unique request ID and session ID that ties together
     * with any application log entries etc.
     *
     * Log entries have a severity set based on http_response_code, to provide a second route for detection/altering of
     * 4xx and 5xx responses.
     *
     * @param array|null $server             The $_SERVER array
     * @param float|null $request_start_time The microtime when the request started processing - usually a const defined at the very start of your PHP
     */
    public function logRequest(?array $server, ?float $request_start_time = NULL): void
    {
        $server    = $server ?: $_SERVER;
        $http_code = http_response_code() ?: '{na}';
        $meta      = $this->getMetadata();
        $log_entry = AssociativeArrayUtils::deepMerge(
            [
                'severity'            => strtoupper($this->getLogPriorityForHttpCode($http_code)),
                self::PROP_INGEN_TYPE => 'rqst',
                'httpRequest'         => $meta['context']['httpRequest'] ?? [],
                'context' => [
                    'mem_mb' => sprintf('%.2f',\memory_get_peak_usage(TRUE) / 1_000_000),
                ],
            ],
            $meta,
            [
                'httpRequest' => [
                    // requestMethod, requestUrl, remoteIp are expected to come from metadata provider as they are
                    // shared with application log entry context
                    'status'    => $http_code,
                    'userAgent' => $this->truncate($server['HTTP_USER_AGENT'] ?? NULL, 500),
                    'latency'   => $this->calculateRequestLatency($request_start_time),
                ],
            ]
        );
        // No need to duplicate the httpRequest in context, we have already given it as a top-level property
        unset($log_entry['context']['httpRequest']);

        $this->writeLog($log_entry);
    }

    private function truncate(?string $value, int $max_byte_length): ?string
    {
        if ($value === NULL) {
            return NULL;
        }

        if (strlen($value) <= $max_byte_length) {
            return $value;
        }

        // Use mb_strcut because we want to limit the byte size but keep a utf-8 valid string:
        // * substr might split a utf8 character and cause an encoding error.
        // * mb_substr could allow a much longer byte length if the string was entirely composed of unicode characters.
        return trim(mb_strcut($value, 0, $max_byte_length)).'…';
    }

    protected function getLogPriorityForHttpCode(string $code): string
    {
        $map = [
            '403'  => LogLevel::WARNING,
            '{na}' => LogLevel::WARNING,
        ];
        if (isset($map[$code])) {
            return $map[$code];
        }

        switch (substr($code, 0, 1)) {
            case '2':
            case '3':
                return LogLevel::INFO;
            case '4':
                return LogLevel::NOTICE;
            case '5':
            default:
                return LogLevel::ERROR;
        }
    }

    protected function calculateRequestLatency(?float $request_start_time)
    {
        if ($request_start_time === NULL) {
            return NULL;
        }

        $request_end_time = microtime(TRUE);

        return sprintf('%0.6fs', $request_end_time - $request_start_time);
    }

}
