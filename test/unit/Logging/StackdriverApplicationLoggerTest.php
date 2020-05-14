<?php


namespace test\unit\Ingenerator\PHPUtils\Logging;

use Ingenerator\PHPUtils\Logging\LoggingFailureException;
use Ingenerator\PHPUtils\Logging\StackdriverApplicationLogger;
use Ingenerator\PHPUtils\StringEncoding\JSON;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class StackdriverApplicationLoggerTest extends TestCase
{

    protected $vfs;

    protected $log_stream;

    protected $meta_args = [];

    public function test_it_is_initialisable()
    {
        $logger = $this->newSubject();
        $this->assertInstanceOf(StackdriverApplicationLogger::class, $logger);
        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }

    public function test_it_throws_if_it_cannot_write_log_entry()
    {
        $previous         = vfsStream::newFile('invalid-log.log')
            ->chmod(000)
            ->at($this->vfs);
        $this->log_stream = $previous->url();

        $subject = $this->newSubject();
        $this->expectException(LoggingFailureException::class);
        $subject->info('Anything breaks it');
    }

    public function provider_log_lines()
    {
        return [
            [
                ['This is my info'],
            ],
            [
                ['This info', 'Another msg'],
            ],
        ];
    }

    /**
     * @dataProvider provider_log_lines
     */
    public function test_it_logs_to_specified_location_as_json_line_with_expected_message($messages)
    {
        $subject = $this->newSubject();
        foreach ($messages as $msg) {
            $subject->info($msg);
        }

        $entries = $this->assertLoggedJSONLines();
        $actual  = \array_map(function (array $msg) { return $msg['message']; }, $entries);
        $this->assertSame($messages, $actual, 'Expect correct log messages');
    }

    public function test_it_appends_logs_to_existing_content_if_file_specified()
    {
        $previous = vfsStream::newFile('existing-log.log')
            ->withContent('{"previous":"content"}'."\n")
            ->at($this->vfs);

        $this->log_stream = $previous->url();

        $this->newSubject()->info('Another message appended');

        $entries = $this->assertLoggedJSONLines();
        $this->assertSame(['previous' => 'content'], \array_shift($entries));
        $this->assertSame('Another message appended', \array_shift($entries)['message']);
    }

    public function provider_severity_levels()
    {
        return [
            [LogLevel::DEBUG, 'DEBUG'],
            [LogLevel::EMERGENCY, 'EMERGENCY'],
        ];
    }

    /**
     * @dataProvider provider_severity_levels
     */
    public function test_it_logs_with_expected_severity($psr_level, $stackdriver_level)
    {
        $this->newSubject()->log($psr_level, 'A message');
        $entry = $this->assertLoggedOneLine();
        $this->assertSame($stackdriver_level, $entry['severity']);
    }

    public function test_it_logs_with_metadata_provided_in_constructor()
    {
        $this->meta_args = [
            ['serviceContext' => ['app' => 'mine', 'ver' => 'this']],
        ];
        $this->newSubject()->info('Anything');
        $entry = $this->assertLoggedOneLine();
        $this->assertSame(
            ['app' => 'mine', 'ver' => 'this'],
            $entry['serviceContext']
        );
    }

    public function test_it_recursively_merges_multiple_metadata_sets_from_constructor()
    {
        $this->meta_args = [
            ['serviceContext' => ['app' => 'mine', 'ver' => 'this']],
            ['serviceContext' => ['ver' => 'other', 'microsite' => 'whatever']],
        ];
        $this->newSubject()->info('Anything');
        $entry = $this->assertLoggedOneLine();
        $this->assertSame(
            ['app' => 'mine', 'ver' => 'other', 'microsite' => 'whatever'],
            $entry['serviceContext']
        );
    }

    public function test_it_lazily_retrieves_metadata_callbacks_for_first_log_call()
    {
        $call_site       = 'immediate';
        $call_count      = 0;
        $this->meta_args = [
            ['lambda' => ['req' => 'ab92823232']],
            function () use (&$call_site, &$call_count) {
                $call_count++;

                return ['lambda' => ['call_site' => $call_site]];
            },
        ];

        $subject = $this->newSubject();
        $this->assertSame(0, $call_count, 'Not called at construction');

        $call_site = 'lazy';
        $subject->info('Anything');
        $this->assertSame(1, $call_count, 'Called during first log');

        $subject->info('More stuff');
        $this->assertSame(1, $call_count, 'Not called again by second log');

        $lines = $this->assertLoggedJSONLines();
        $this->assertCount(2, $lines, 'Logged 2 lines');
        foreach ($lines as $idx => $line) {
            $this->assertSame(
                ['req' => 'ab92823232', 'call_site' => 'lazy'],
                $line['lambda'],
                'Expect correct meta line '.$idx
            );
        }
    }

    public function test_it_can_lazily_retrieve_metadata_from_provider_class()
    {
        $meta_provider_class = new class implements \Ingenerator\PHPUtils\Logging\LogMetadataProvider {
            protected $call_count = 0;

            public function getMetadata(): array
            {
                return ['context' => ['call_count' => ++$this->call_count]];
            }

            public function getCallCount()
            {
                return $this->call_count;
            }
        };

        $this->meta_args = [
            ['context' => ['req' => 'ab92823232']],
            $meta_provider_class,
        ];

        $subject = $this->newSubject();
        $this->assertSame(0, $meta_provider_class->getCallCount(), 'Not called at construction');

        $subject->info('Anything');
        $this->assertSame(1, $meta_provider_class->getCallCount(), 'Called during first log');

        $subject->info('More stuff');
        $this->assertSame(1, $meta_provider_class->getCallCount(), 'Not called again by second log');

        $lines = $this->assertLoggedJSONLines();
        $this->assertCount(2, $lines, 'Logged 2 lines');
        foreach ($lines as $idx => $line) {
            $this->assertSame(
                ['req' => 'ab92823232', 'call_count' => 1],
                $line['context'],
                'Expect correct meta line '.$idx
            );
        }
    }

    public function test_it_swallows_and_logs_any_errors_while_retrieving_metadata_callbacks()
    {
        $this->meta_args = [
            ['sources' => ['first_ok']],
            function () { return new \DateTimeImmutable(new \stdClass); },
            ['sources' => ['third_ok']],
            function () { throw new \InvalidArgumentException('Broken'); },
            ['sources' => ['fifth_ok']],
            new class implements \Ingenerator\PHPUtils\Logging\LogMetadataProvider {
                public function getMetadata(): array
                {
                    throw new \BadMethodCallException('The class broke');
                }
            },
            ['sources' => ['seventh_ok']],
        ];

        $this->newSubject()->info('I am an info');

        $lines = $this->assertLoggedJSONLines();

        $actual = [];
        foreach ($lines as $line) {
            $this->assertSame(
                ['first_ok', 'third_ok', 'fifth_ok', 'seventh_ok'],
                $line['sources'],
                'Should log best-effort metadata for all entries'
            );
            $actual[] = $line['severity'].': '.$line['message'];
        }
        $this->assertSame(
            [
                'ALERT: Invalid log metadata source#1 [TypeError] DateTimeImmutable::__construct() expects parameter 1 to be string, object given',
                'ALERT: Invalid log metadata source#3 [InvalidArgumentException] Broken',
                'ALERT: Invalid log metadata source#5 [BadMethodCallException] The class broke',
                'INFO: I am an info',
            ],
            $actual
        );
    }

    /**
     * @testWith [[], "app"]
     *           [{"@ingenType": "rqst"}, "rqst"]
     */
    public function test_it_assigns_ingenerator_type_unless_overridden($context, $expect_type)
    {
        $this->newSubject()->warning('Anything', $context);
        $entry = $this->assertLoggedOneLine();
        $this->assertSame($expect_type, $entry['@ingenType']);
    }

    /**
     * @testWith ["log", ["info", "Some info"]]
     *           ["info", ["Some info"]]
     */
    public function test_it_logs_with_source_location_as_immediate_caller($log_method, $args)
    {
        $expect_line = __LINE__ + 1;
        \call_user_func_array([$this->newSubject(), $log_method], $args);
        $entry = $this->assertLoggedOneLine();
        $this->assertSame(
            [
                'file'     => __FILE__,
                'line'     => $expect_line,
                'function' => __CLASS__.'->'.__FUNCTION__,
            ],
            $entry['logging.googleapis.com/sourceLocation']
        );
    }

    public function test_it_allows_caller_to_override_source_location()
    {
        $this->newSubject()->info(
            'Any message',
            ['logging.googleapis.com/sourceLocation' => ['file' => 'anything.php', 'line' => 9213]]
        );
        $entry = $this->assertLoggedOneLine();
        $this->assertSame(
            ['file' => 'anything.php', 'line' => 9213],
            $entry['logging.googleapis.com/sourceLocation']
        );
    }

    public function test_it_includes_arbitrary_custom_context_under_namespaced_key()
    {
        // If we allow the app to override our standard keys / metadata etc there is a high risk of unexpected and
        // undetected data loss particularly as the logger will often be provided to / called by third-party code.
        $this->meta_args = [
            ['context' => ['user' => 'me', 'sess' => 'sass']],
        ];
        $this->newSubject()->info(
            'Any message',
            ['context' => ['user' => 'her', 'page' => 'ourpage']]
        );
        $entry = $this->assertLoggedOneLine();
        $this->assertSame(
            ['context' => ['user' => 'her', 'page' => 'ourpage']],
            $entry['custom_context']
        );
        $this->assertSame(
            ['user' => 'me', 'sess' => 'sass'],
            $entry['context']
        );
    }

    public function test_it_publishes_exception_string_in_context_without_issues()
    {
        $this->newSubject()->info(
            'Any message',
            ['exception' => 'Well now, this is a string']
        );
        $entry = $this->assertLoggedOneLine();
        $this->assertSame(
            'Well now, this is a string',
            $entry['exception']
        );
    }

    public function provider_exception_chains()
    {
        $e1      = new \InvalidArgumentException('I am an exception', 102);
        $e1_line = __LINE__ - 1;

        $e2      = new \RuntimeException('It went wrong', 02, $e1);
        $e2_line = __LINE__ - 1;

        $e3      = new \TypeError('Stuff happened', 02, $e2);
        $e3_line = __LINE__ - 1;

        return [
            [
                $e1,
                [
                    'class' => \InvalidArgumentException::class,
                    'msg'   => 'I am an exception',
                    'code'  => 102,
                    'file'  => __FILE__,
                    'line'  => $e1_line,
                    'trace' => $this->makeExpectedSanitisedTrace($e1),
                ],
            ],
            [
                $e3,
                [
                    'class'    => \TypeError::class,
                    'msg'      => 'Stuff happened',
                    'code'     => 02,
                    'file'     => __FILE__,
                    'line'     => $e3_line,
                    'trace'    => $this->makeExpectedSanitisedTrace($e1),
                    'previous' => [
                        'class'    => \RuntimeException::class,
                        'msg'      => 'It went wrong',
                        'code'     => 02,
                        'file'     => __FILE__,
                        'line'     => $e2_line,
                        'trace'    => $this->makeExpectedSanitisedTrace($e2),
                        'previous' => [
                            'class' => \InvalidArgumentException::class,
                            'msg'   => 'I am an exception',
                            'code'  => 102,
                            'file'  => __FILE__,
                            'line'  => $e1_line,
                            'trace' => $this->makeExpectedSanitisedTrace($e3),
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider provider_exception_chains
     */
    public function test_it_recursively_formats_exception_chain_as_structs_with_trace_excluding_args($e, $expect)
    {
        $this->newSubject()->info('Broken', ['exception' => $e]);
        $entry = $this->assertLoggedOneLine();
        $this->assertSame($expect, $entry['exception']);
    }

    public function test_it_reports_exception_as_stackdriver_error_by_default_with_stack_trace_in_stackdriver_format()
    {
        // https://cloud.google.com/error-reporting/reference/rest/v1beta1/ErrorContext
        // https://cloud.google.com/error-reporting/reference/rest/v1beta1/projects.events/report#ReportedErrorEvent
        $e = new \RuntimeException('There was a problem');

        $this->newSubject()->info('Arg', ['exception' => $e]);
        $entry = $this->assertLoggedOneLine();
        $this->assertSame('Arg', $entry['message'], 'Should not overwrite message');
        $this->assertSame(
            StackdriverApplicationLogger::TYPE_STACKDRIVER_ERROR,
            $entry['@type'],
            'Should mark as stackdriver error'
        );

        // Oh look, Google use different keys for the same values in different places. Thanks @google
        $source_location = $entry[StackdriverApplicationLogger::PROP_SOURCE_LOCATION];
        $this->assertSame(
            [
                'filePath'     => $source_location['file'],
                'lineNumber'   => $source_location['line'],
                'functionName' => $source_location['function'],
            ],
            $entry['context']['reportLocation'],
            'Should copy sourceLocation to context.reportLocation'
        );

        // Error Reporting requirements for PHP:
        // Must start with PHP (Notice|Parse error|Fatal error|Warning) and contain the result of (string)$exception.
        // We build an equivalent string but with our sanitised trace and put it in stack_trace which is where
        // Stackdriver Errors looks first.
        // This does mean we are duplicating this text on every log entry that contains an exception, which will add up
        // over time...
        $expect_text = 'PHP Warning: '.\preg_replace(
                '/Stack trace:.+$/s',
                "Stack trace:\n".$this->makeExpectedSanitisedTrace($e),
                (string) $e
            );
        $this->assertSame($expect_text, $entry['stack_trace']);
    }

    public function test_it_does_not_report_exception_as_stackdriver_error_if_flag_overriden()
    {
        $e = new \RuntimeException('There was a problem');
        $this->newSubject()->info(
            'Arg',
            ['exception' => $e, StackdriverApplicationLogger::PROP_REPORT_STACKDRIVER_ERROR => FALSE]
        );
        $entry = $this->assertLoggedOneLine();
        $this->assertArrayNotHasKey('@type', $entry);
        $this->assertArrayNotHasKey('stack_trace', $entry);
        $this->assertNull($entry['context']['reportLocation'] ?? NULL, 'Should not have context.reportLocation');
    }

    public function test_it_can_optionally_report_stackdriver_error_without_exception()
    {
        $this->newSubject()->info(
            'Any old problem we want to report',
            [StackdriverApplicationLogger::PROP_REPORT_STACKDRIVER_ERROR => TRUE]
        );
        $entry = $this->assertLoggedOneLine();

        $this->assertSame(
            StackdriverApplicationLogger::TYPE_STACKDRIVER_ERROR,
            $entry['@type'],
            'Should mark as stackdriver error'
        );
        $this->assertClonesSourceLocationToContextReportLocation($entry);
        $this->assertArrayNotHasKey('stack_trace', $entry, 'Should not have a stack_trace property');
        $this->assertArrayNotHasKey('exception', $entry, 'Should not have an exception property');
    }

    public function test_its_request_logger_logs_request_as_ingenerator_request_log_type()
    {
        $this->newSubject()->logRequest([]);
        $entry = $this->assertLoggedOneLine();
        $this->assertSame('rqst', $entry['@ingenType']);
    }

    public function provider_request_status_level()
    {
        return [
            [200, 'INFO'],
            [202, 'INFO'],
            [400, 'NOTICE'],
            [403, 'WARNING'],
            [422, 'NOTICE'],
            [500, 'ERROR'],
            [502, 'ERROR'],
        ];
    }

    /**
     * @dataProvider provider_request_status_level
     */
    public function test_its_request_logger_assigns_loglevel_based_on_http_response_code($http_status, $expect)
    {
        $logger = $this->newSubject();
        \http_response_code($http_status);
        $logger->logRequest([]);
        $entry = $this->assertLoggedOneLine();
        $this->assertSame($expect, $entry['severity'], 'Expect correct severity');
    }

    public function test_its_request_logger_hoists_http_metadata_to_top_level()
    {
        $original_http_meta = [
            'requestMethod' => 'POST',
            'requestUrl'    => '/logger.php',
            'remoteIp'      => '0.5.3.2',
        ];
        $this->meta_args    = [
            ['context' => ['httpRequest' => $original_http_meta,]],
        ];

        $logger = $this->newSubject();
        $logger->logRequest([]);
        $entry = $this->assertLoggedOneLine();
        $this->assertSame(
            $original_http_meta,
            \array_intersect_key($entry['httpRequest'], $original_http_meta),
            'Should include metadata in top-level httpRequest struct'
        );
        $this->assertFalse(isset($entry['context']['httpRequest']), 'Should not duplicate httpRequest meta in context');
    }

    /**
     * @testWith ["message"]
     *           ["logging.googleapis.com/sourceLocation"]
     */
    public function test_its_request_logger_does_not_log_irrelevant_properties($prop)
    {
        $this->newSubject()->logRequest([]);
        $entry = $this->assertLoggedOneLine();
        $this->assertArrayNotHasKey($prop, $entry);
    }

    public function test_its_request_logger_logs_custom_metadata()
    {
        $this->meta_args = [
            ['context' => ['user' => 'john@do.com']],
        ];

        $logger = $this->newSubject();
        $logger->logRequest([]);
        $entry = $this->assertLoggedOneLine();
        $this->assertSame(
            ['user' => 'john@do.com'],
            $entry['context']
        );
    }

    public function test_its_request_logger_logs_http_response_code()
    {
        \http_response_code(402);
        $logger = $this->newSubject();
        $logger->logRequest([]);
        $entry = $this->assertLoggedOneLine();
        $this->assertSame(402, $entry['httpRequest']['status']);
    }

    /**
     * @testWith [[], null]
     *           [{"HTTP_USER_AGENT": "chrome 10"}, "chrome 10"]
     */
    public function test_its_request_logger_logs_user_agent_from_global_array($server, $expect)
    {
        \http_response_code(402);
        $logger = $this->newSubject();
        $logger->logRequest($server);
        $entry = $this->assertLoggedOneLine();
        $this->assertSame($expect, $entry['httpRequest']['userAgent']);
    }

    public function test_its_request_logger_logs_latency_since_start_time_if_provided()
    {
        $start  = \microtime(TRUE);
        $logger = $this->newSubject();
        $logger->logRequest([], $start);
        $end   = \microtime(TRUE);
        $entry = $this->assertLoggedOneLine();
        $this->assertRegExp('/^(0\.[0-9]+)s$/', $entry['httpRequest']['latency']);
        $seconds = (float) \str_replace('s', '', $entry['httpRequest']['latency']);
        $this->assertEqualsWithDelta($end - $start, $seconds, 0.1);
    }

    public function test_its_request_logger_logs_null_latency_if_no_start_time()
    {
        $logger = $this->newSubject();
        $logger->logRequest([]);
        $entry = $this->assertLoggedOneLine();
        $this->assertNull($entry['httpRequest']['latency']);
    }

    /**
     * @return array
     */
    protected function assertLoggedJSONLines(): array
    {
        if ( ! \is_file($this->log_stream)) {
            $this->fail('Log stream '.$this->log_stream.' does not exist');
        }

        $content = \file_get_contents($this->log_stream);
        $lines   = \explode("\n", $content);
        $this->assertSame("", \array_pop($lines), 'Expect trailing newline after last message');

        return \array_map(
            function (string $line) { return JSON::decodeArray($line); },
            $lines
        );
    }

    /**
     * @return array
     */
    protected function assertLoggedOneLine(): array
    {
        $entries = $this->assertLoggedJSONLines();
        $this->assertCount(1, $entries, 'Expected a single log entry');

        return \array_shift($entries);
    }

    protected function makeExpectedSanitisedTrace(\Throwable $e): string
    {
        // Build an expected trace by cleaning up the args from the original exception string
        // This makes our test safe against changes in the PHPUnit callstack above this method,
        // while also ensuring the code continues to generate the same format of output as the PHP
        // native trace string.
        $expect_trace = implode(
            "\n",
            array_map(
                function ($trace_line) {
                    $parts = explode(' ', $trace_line, 3);
                    if (isset($parts[2])) {
                        // Strip args from the string
                        $parts[2] = \preg_replace('/\(.+\)$/', '()', $parts[2]);
                    }

                    return implode(' ', $parts);
                },
                explode("\n", $e->getTraceAsString())
            )
        );

        return $expect_trace;
    }

    /**
     * @param array $entry
     */
    protected function assertClonesSourceLocationToContextReportLocation(array $entry): void
    {
        // Oh look, Google use different keys for the same values in different places. Thanks @google
        $source_location = $entry[StackdriverApplicationLogger::PROP_SOURCE_LOCATION];
        $this->assertSame(
            [
                'filePath'     => $source_location['file'],
                'lineNumber'   => $source_location['line'],
                'functionName' => $source_location['function'],
            ],
            $entry['context']['reportLocation'],
            'Should copy sourceLocation to context.reportLocation'
        );
    }

    protected function setUp()
    {
        parent::setUp();
        $this->vfs        = vfsStream::setup();
        $this->log_stream = $this->vfs->url().'/log-str.log';
    }


    protected function newSubject(): StackdriverApplicationLogger
    {
        return new \Ingenerator\PHPUtils\Logging\StackdriverApplicationLogger(
            $this->log_stream,
            ...$this->meta_args
        );
    }

}

