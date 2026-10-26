<?php

declare(strict_types=1);

namespace test\unit\Ingenerator\PHPUtils\Logging;

use BadMethodCallException;
use DateTimeImmutable;
use Ingenerator\PHPUtils\Logging\LoggingFailureException;
use Ingenerator\PHPUtils\Logging\LogMetadataProvider;
use Ingenerator\PHPUtils\Logging\StackdriverApplicationLogger;
use Ingenerator\PHPUtils\StringEncoding\JSON;
use InvalidArgumentException;
use Iterator;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use RuntimeException;
use stdClass;
use Throwable;
use TypeError;

use function array_intersect_key;
use function array_map;
use function array_pop;
use function array_shift;
use function explode;
use function file_get_contents;
use function http_response_code;
use function is_file;
use function memory_get_peak_usage;
use function microtime;
use function preg_replace;
use function str_replace;

final class StackdriverApplicationLoggerTest extends TestCase
{
    private vfsStreamDirectory $vfs;

    private string $log_stream;

    /** @var list<array<string, mixed>|callable(): array<string, mixed>|LogMetadataProvider> */
    private array $meta_args = [];

    public function testItIsInitialisable(): void
    {
        $logger = $this->newSubject();
        $this->assertInstanceOf(StackdriverApplicationLogger::class, $logger);
        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }

    public function testItThrowsIfItCannotWriteLogEntry(): void
    {
        $previous = vfsStream::newFile('invalid-log.log')
            ->chmod(000)
            ->at($this->vfs);
        $this->log_stream = $previous->url();

        $subject = $this->newSubject();
        $this->expectException(LoggingFailureException::class);
        $subject->info('Anything breaks it');
    }

    /**
     * @return Iterator<int, array<int, array<string>>>
     */
    public static function provider_log_lines(): Iterator
    {
        yield [
            ['This is my info'],
        ];
        yield [
            ['This info', 'Another msg'],
        ];
    }

    /** @param list<string> $messages */
    #[DataProvider('provider_log_lines')]
    public function testItLogsToSpecifiedLocationAsJsonLineWithExpectedMessage(array $messages): void
    {
        $subject = $this->newSubject();
        foreach ($messages as $msg) {
            $subject->info($msg);
        }

        $entries = $this->assertLoggedJSONLines();
        $actual = array_map(fn (array $msg): mixed => $msg['message'], $entries);
        $this->assertSame($messages, $actual, 'Expect correct log messages');
    }

    public function testItHandlesInvalidUtf8WhenLogging(): void
    {
        $subject = $this->newSubject();
        $line = __LINE__ + 1;
        $subject->info("This got truncated somewhere\xD0 then appended to", ['and' => "this was junk\xa3\xa9"]);
        $entries = $this->assertLoggedJSONLines();
        $this->assertSame(
            [
                [
                    'severity' => 'INFO',
                    'message' => 'This got truncated somewhere� then appended to',
                    '@ingenType' => 'app',
                    'logging.googleapis.com/sourceLocation' => [
                        'file' => __FILE__,
                        'line' => $line,
                        'function' => self::class.'->'.__FUNCTION__,
                    ],
                    'custom_context' => [
                        'and' => 'this was junk��',
                    ],
                ],
            ],
            $entries
        );
    }

    public function testItAppendsLogsToExistingContentIfFileSpecified(): void
    {
        $previous = vfsStream::newFile('existing-log.log')
            ->withContent('{"previous":"content"}'."\n")
            ->at($this->vfs);

        $this->log_stream = $previous->url();

        $this->newSubject()->info('Another message appended');

        $entries = $this->assertLoggedJSONLines();
        $this->assertSame(['previous' => 'content'], array_shift($entries));
        $this->assertSame('Another message appended', array_shift($entries)['message']);
    }

    /**
     * @return Iterator<int, array<string>>
     */
    public static function provider_severity_levels(): Iterator
    {
        yield [LogLevel::DEBUG, 'DEBUG'];
        yield [LogLevel::EMERGENCY, 'EMERGENCY'];
    }

    #[DataProvider('provider_severity_levels')]
    public function testItLogsWithExpectedSeverity(string $psr_level, string $stackdriver_level): void
    {
        $this->newSubject()->log($psr_level, 'A message');
        $entry = $this->assertLoggedOneLine();
        $this->assertSame($stackdriver_level, $entry['severity']);
    }

    public function testItLogsWithMetadataProvidedInConstructor(): void
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

    public function testItRecursivelyMergesMultipleMetadataSetsFromConstructor(): void
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

    public function testItLazilyRetrievesMetadataCallbacksForFirstLogCall(): void
    {
        $call_site = 'immediate';
        $call_count = 0;
        $this->meta_args = [
            ['lambda' => ['req' => 'ab92823232']],
            function () use (&$call_site, &$call_count): array {
                ++$call_count;

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

    public function testItCanLazilyRetrieveMetadataFromProviderClass(): void
    {
        $meta_provider_class = new class implements LogMetadataProvider {
            protected int $call_count = 0;

            /** @return array<string, mixed> */
            public function getMetadata(): array
            {
                return ['context' => ['call_count' => ++$this->call_count]];
            }

            public function getCallCount(): int
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

    public function testItSwallowsAndLogsAnyErrorsWhileRetrievingMetadataCallbacks(): void
    {
        // @phpstan-ignore assign.propertyType (this test deliberately supplies malformed metadata callbacks)
        $this->meta_args = [
            ['sources' => ['first_ok']],
            /* @phpstan-ignore argument.type (intentional, to make it have an error) */
            fn (): DateTimeImmutable => new DateTimeImmutable(new stdClass()),
            ['sources' => ['third_ok']],
            function (): never { throw new InvalidArgumentException('Broken'); },
            ['sources' => ['fifth_ok']],
            new class implements LogMetadataProvider {
                /** @return array<string, mixed> */
                public function getMetadata(): array
                {
                    throw new BadMethodCallException('The class broke');
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
                'ALERT: Invalid log metadata source#1 [TypeError] DateTimeImmutable::__construct(): Argument #1 ($datetime) must be of type string, stdClass given',
                'ALERT: Invalid log metadata source#3 [InvalidArgumentException] Broken',
                'ALERT: Invalid log metadata source#5 [BadMethodCallException] The class broke',
                'INFO: I am an info',
            ],
            $actual
        );
    }

    /** @param array<string, string> $context */
    #[TestWith([[], 'app'])]
    #[TestWith([['@ingenType' => 'rqst'], 'rqst'])]
    public function testItAssignsIngeneratorTypeUnlessOverridden(array $context, string $expect_type): void
    {
        $this->newSubject()->warning('Anything', $context);
        $entry = $this->assertLoggedOneLine();
        $this->assertSame($expect_type, $entry['@ingenType']);
    }

    /** @param list<string> $args */
    #[TestWith(['log', ['info', 'Some info']])]
    #[TestWith(['info', ['Some info']])]
    public function testItLogsWithSourceLocationAsImmediateCaller(string $log_method, array $args): void
    {
        $logger = $this->newSubject();
        if ($log_method === 'log') {
            $expect_line = __LINE__ + 1;
            $logger->log(...$args);
        } else {
            $expect_line = __LINE__ + 1;
            $logger->info(...$args);
        }
        $entry = $this->assertLoggedOneLine();
        $this->assertSame(
            [
                'file' => __FILE__,
                'line' => $expect_line,
                'function' => self::class.'->'.__FUNCTION__,
            ],
            $entry['logging.googleapis.com/sourceLocation']
        );
    }

    public function testItAllowsCallerToOverrideSourceLocation(): void
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

    public function testItIncludesArbitraryCustomContextUnderNamespacedKey(): void
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

    public function testItPublishesExceptionStringInContextWithoutIssues(): void
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

    /**
     * @return Iterator<int, array<mixed>>
     */
    public static function provider_exception_chains(): Iterator
    {
        $e1 = new InvalidArgumentException('I am an exception', 102);
        $e1_line = __LINE__ - 1;

        $e2 = new RuntimeException('It went wrong', 02, $e1);
        $e2_line = __LINE__ - 1;

        $e3 = new TypeError('Stuff happened', 02, $e2);
        $e3_line = __LINE__ - 1;
        yield [
            $e1,
            [
                'class' => InvalidArgumentException::class,
                'msg' => 'I am an exception',
                'code' => 102,
                'file' => __FILE__,
                'line' => $e1_line,
                'trace' => self::makeExpectedSanitisedTrace($e1),
            ],
        ];
        yield [
            $e3,
            [
                'class' => TypeError::class,
                'msg' => 'Stuff happened',
                'code' => 02,
                'file' => __FILE__,
                'line' => $e3_line,
                'trace' => self::makeExpectedSanitisedTrace($e1),
                'previous' => [
                    'class' => RuntimeException::class,
                    'msg' => 'It went wrong',
                    'code' => 02,
                    'file' => __FILE__,
                    'line' => $e2_line,
                    'trace' => self::makeExpectedSanitisedTrace($e2),
                    'previous' => [
                        'class' => InvalidArgumentException::class,
                        'msg' => 'I am an exception',
                        'code' => 102,
                        'file' => __FILE__,
                        'line' => $e1_line,
                        'trace' => self::makeExpectedSanitisedTrace($e3),
                    ],
                ],
            ],
        ];
    }

    /** @param array<mixed> $expect */
    #[DataProvider('provider_exception_chains')]
    public function testItRecursivelyFormatsExceptionChainAsStructsWithTraceExcludingArgs(InvalidArgumentException|TypeError $e, array $expect): void
    {
        $this->newSubject()->info('Broken', ['exception' => $e]);
        $entry = $this->assertLoggedOneLine();
        $this->assertSame($expect, $entry['exception']);
    }

    public function testItReportsExceptionAsStackdriverErrorByDefaultWithStackTraceInStackdriverFormat(): void
    {
        // https://cloud.google.com/error-reporting/reference/rest/v1beta1/ErrorContext
        // https://cloud.google.com/error-reporting/reference/rest/v1beta1/projects.events/report#ReportedErrorEvent
        $e = new RuntimeException('There was a problem');

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
                'filePath' => $source_location['file'],
                'lineNumber' => $source_location['line'],
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
        $expect_text = 'PHP Warning: '.preg_replace(
            '/Stack trace:.+$/s',
            "Stack trace:\n".self::makeExpectedSanitisedTrace($e),
            (string) $e
        );
        $this->assertSame($expect_text, $entry['stack_trace']);
    }

    public function testItDoesNotReportExceptionAsStackdriverErrorIfFlagOverriden(): void
    {
        $e = new RuntimeException('There was a problem');
        $this->newSubject()->info(
            'Arg',
            ['exception' => $e, StackdriverApplicationLogger::PROP_REPORT_STACKDRIVER_ERROR => false]
        );
        $entry = $this->assertLoggedOneLine();
        $this->assertArrayNotHasKey('@type', $entry);
        $this->assertArrayNotHasKey('stack_trace', $entry);
        $this->assertNull($entry['context']['reportLocation'] ?? null, 'Should not have context.reportLocation');
    }

    public function testItCanOptionallyReportStackdriverErrorWithoutException(): void
    {
        $this->newSubject()->info(
            'Any old problem we want to report',
            [StackdriverApplicationLogger::PROP_REPORT_STACKDRIVER_ERROR => true]
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

    public function testItsRequestLoggerLogsRequestAsIngeneratorRequestLogType(): void
    {
        $this->newSubject()->logRequest([]);
        $entry = $this->assertLoggedOneLine();
        $this->assertSame('rqst', $entry['@ingenType']);
    }

    /**
     * @return Iterator<int, array<(int | string)>>
     */
    public static function provider_request_status_level(): Iterator
    {
        yield [200, 'INFO'];
        yield [202, 'INFO'];
        yield [400, 'NOTICE'];
        yield [403, 'WARNING'];
        yield [422, 'NOTICE'];
        yield [500, 'ERROR'];
        yield [502, 'ERROR'];
    }

    #[DataProvider('provider_request_status_level')]
    public function testItsRequestLoggerAssignsLoglevelBasedOnHttpResponseCode(int $http_status, string $expect): void
    {
        $logger = $this->newSubject();
        http_response_code($http_status);
        $logger->logRequest([]);
        $entry = $this->assertLoggedOneLine();
        $this->assertSame($expect, $entry['severity'], 'Expect correct severity');
    }

    public function testItsRequestLoggerHoistsHttpMetadataToTopLevel(): void
    {
        $original_http_meta = [
            'requestMethod' => 'POST',
            'requestUrl' => '/logger.php',
            'remoteIp' => '0.5.3.2',
        ];
        $this->meta_args = [
            ['context' => ['httpRequest' => $original_http_meta]],
        ];

        $logger = $this->newSubject();
        $logger->logRequest([]);
        $entry = $this->assertLoggedOneLine();
        $this->assertSame(
            $original_http_meta,
            array_intersect_key($entry['httpRequest'], $original_http_meta),
            'Should include metadata in top-level httpRequest struct'
        );
        $this->assertArrayNotHasKey('httpRequest', $entry['context'], 'Should not duplicate httpRequest meta in context');
    }

    #[TestWith(['message'])]
    #[TestWith(['logging.googleapis.com/sourceLocation'])]
    public function testItsRequestLoggerDoesNotLogIrrelevantProperties(string $prop): void
    {
        $this->newSubject()->logRequest([]);
        $entry = $this->assertLoggedOneLine();
        $this->assertArrayNotHasKey($prop, $entry);
    }

    public function testItsRequestLoggerLogsCustomMetadata(): void
    {
        $this->meta_args = [
            ['context' => ['user' => 'john@do.com']],
        ];

        $logger = $this->newSubject();
        $logger->logRequest([]);
        $entry = $this->assertLoggedOneLine();
        $this->assertSame(['mem_mb', 'user'], array_keys($entry['context']));
        $this->assertSame('john@do.com', $entry['context']['user']);
    }

    public function testItsRequestLoggerLogsHttpResponseCode(): void
    {
        http_response_code(402);
        $logger = $this->newSubject();
        $logger->logRequest([]);
        $entry = $this->assertLoggedOneLine();
        $this->assertSame(402, $entry['httpRequest']['status']);
    }

    /** @param array<string, string> $server */
    #[TestWith([[], ''])]
    #[TestWith([['HTTP_USER_AGENT' => 'chrome 10'], 'chrome 10'])]
    #[TestWith([
        ['HTTP_USER_AGENT' => "Mozilla/5.0 (compatible; Baiduspider/2.0; \xa3\xa9 and more stuff"],
        'Mozilla/5.0 (compatible; Baiduspider/2.0; �� and more stuff',
    ])]
    public function testItsRequestLoggerLogsUserAgentFromGlobalArray(array $server, string $expect): void
    {
        http_response_code(402);
        $logger = $this->newSubject();
        $logger->logRequest($server);
        $entry = $this->assertLoggedOneLine();
        $this->assertSame($expect, $entry['httpRequest']['userAgent']);
    }

    public function testItsRequestLoggerTruncatesLongUserAgents(): void
    {
        $ua_val = 'abcdefghijklmnopqrstuvwxyz 1234567890 abcdefghijklmnopqrstuvwxyz 1234567890 abcdefghijklmnopqrstuvwxyz 1234567890 abcdefghijklmnopqrstuvwxyz 1234567890 abcdefghijklmnopqrstuvwxyz 1234567890 abcdefghijklmnopqrstuvwxyz 1234567890 abcdefghijklmnopqrstuvwxyz 1234567890 abcdefghijklmnopqrstuvwxyz 1234567890 abcdefghijklmnopqrstuvwxyz 1234567890 abcdefghijklmnopqrstuvwxyz 1234567890 abcdefghijklmnopqrstuvwxyz 1234567890 abcdefghijklmnopqrstuvwxyz 1234567890 abcdefghijklmnopqrstuvwxyz 1234567890 long ※ note the utf8 safe truncation and trimming';
        $expect = 'abcdefghijklmnopqrstuvwxyz 1234567890 abcdefghijklmnopqrstuvwxyz 1234567890 abcdefghijklmnopqrstuvwxyz 1234567890 abcdefghijklmnopqrstuvwxyz 1234567890 abcdefghijklmnopqrstuvwxyz 1234567890 abcdefghijklmnopqrstuvwxyz 1234567890 abcdefghijklmnopqrstuvwxyz 1234567890 abcdefghijklmnopqrstuvwxyz 1234567890 abcdefghijklmnopqrstuvwxyz 1234567890 abcdefghijklmnopqrstuvwxyz 1234567890 abcdefghijklmnopqrstuvwxyz 1234567890 abcdefghijklmnopqrstuvwxyz 1234567890 abcdefghijklmnopqrstuvwxyz 1234567890 long…';
        $logger = $this->newSubject();
        $logger->logRequest(['HTTP_USER_AGENT' => $ua_val]);
        $entry = $this->assertLoggedOneLine();
        $this->assertSame($expect, $entry['httpRequest']['userAgent']);
    }

    public function testItsRequestLoggerLogsLatencySinceStartTimeIfProvided(): void
    {
        $start = microtime(true);
        $logger = $this->newSubject();
        $logger->logRequest([], $start);
        $end = microtime(true);
        $entry = $this->assertLoggedOneLine();
        $this->assertMatchesRegularExpression('/^(0\.[0-9]+)s$/', $entry['httpRequest']['latency']);
        $seconds = (float) str_replace('s', '', $entry['httpRequest']['latency']);
        $this->assertEqualsWithDelta($end - $start, $seconds, 0.1);
    }

    public function testItsRequestLoggerLogsNullLatencyIfNoStartTime(): void
    {
        $logger = $this->newSubject();
        $logger->logRequest([]);
        $entry = $this->assertLoggedOneLine();
        $this->assertNull($entry['httpRequest']['latency']);
    }

    public function testItsRequestLoggerLogsPeakMemoryUsageInMb(): void
    {
        $logger = $this->newSubject();
        $peak_before = memory_get_peak_usage(true);
        $logger->logRequest([]);
        $peak_after = memory_get_peak_usage(true);
        $entry = $this->assertLoggedOneLine();
        $this->assertIsString(
            $entry['context']['mem_mb'],
            'Encode memory as string to avoid risk of JSON serialization precision increasing payload size'
        );
        $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', $entry['context']['mem_mb']);

        $logged_meg = $entry['context']['mem_mb'];
        // Sanity check that it's definitely logging in megabytes. This is tricky as there's no way to stub the actual
        // memory usage that the logger saw - but it must be no less than the value before we called it, and no more
        // than the value immediately after we called it
        $this->assertTrue(
            $logged_meg >= round($peak_before / 1000 / 1000, 2) && $logged_meg <= round($peak_after / 1000 / 1000, 2),
            "Logged value should be within $peak_before and $peak_after and in the right units"
        );
    }

    /** @return list<array<string, mixed>> */
    protected function assertLoggedJSONLines(): array
    {
        if (!is_file($this->log_stream)) {
            $this->fail('Log stream '.$this->log_stream.' does not exist');
        }

        $content = file_get_contents($this->log_stream);
        if ($content === false) {
            throw new RuntimeException('Unable to read log stream '.$this->log_stream);
        }
        $lines = explode("\n", $content);
        $this->assertSame('', array_pop($lines), 'Expect trailing newline after last message');

        return array_map(
            JSON::decodeArray(...),
            $lines
        );
    }

    /** @return array<string, mixed> */
    protected function assertLoggedOneLine(): array
    {
        $entries = $this->assertLoggedJSONLines();
        $this->assertCount(1, $entries, 'Expected a single log entry');

        $entry = array_shift($entries);
        // Could only happen if PHP or PHPUnit was broken, we asserted a nonzero array above
        $this->assertNotNull($entry, 'Expected a single log entry');

        return $entry;
    }

    protected static function makeExpectedSanitisedTrace(Throwable $e): string
    {
        // Build an expected trace by cleaning up the args from the original exception string
        // This makes our test safe against changes in the PHPUnit callstack above this method,
        // while also ensuring the code continues to generate the same format of output as the PHP
        // native trace string.
        $expect_trace = implode(
            "\n",
            array_map(
                function ($trace_line): string {
                    $parts = explode(' ', $trace_line, 3);
                    if (isset($parts[2])) {
                        // Strip args from the string
                        $parts[2] = preg_replace('/\(.+\)$/', '()', $parts[2]);
                    }

                    return implode(' ', $parts);
                },
                explode("\n", $e->getTraceAsString())
            )
        );

        return $expect_trace;
    }

    /**
     * @param array<string, mixed> $entry
     */
    protected function assertClonesSourceLocationToContextReportLocation(array $entry): void
    {
        // Oh look, Google use different keys for the same values in different places. Thanks @google
        $source_location = $entry[StackdriverApplicationLogger::PROP_SOURCE_LOCATION];
        $this->assertSame(
            [
                'filePath' => $source_location['file'],
                'lineNumber' => $source_location['line'],
                'functionName' => $source_location['function'],
            ],
            $entry['context']['reportLocation'],
            'Should copy sourceLocation to context.reportLocation'
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->vfs = vfsStream::setup();
        $this->log_stream = $this->vfs->url().'/log-str.log';
    }

    protected function newSubject(): StackdriverApplicationLogger
    {
        return new StackdriverApplicationLogger(
            $this->log_stream,
            ...$this->meta_args
        );
    }
}
