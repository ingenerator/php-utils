<?php
/**
 * @author    Craig Gosman <craig@ingenerator.com>
 * @licence   BSD-3-Clause
 */


namespace test\unit\Ingenerator\PHPUtils\unit\Monitoring;


use Ingenerator\PHPUtils\DateTime\Clock\RealtimeClock;
use Ingenerator\PHPUtils\DateTime\Clock\StoppedMockClock;
use Ingenerator\PHPUtils\Monitoring\ArrayMetricsAgent;
use Ingenerator\PHPUtils\Monitoring\AssertMetrics;
use Ingenerator\PHPUtils\Monitoring\MetricId;
use Ingenerator\PHPUtils\Monitoring\OperationTimer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class OperationTimerTest extends TestCase
{
    protected ArrayMetricsAgent $metrics;
    protected ?RealtimeClock $real_time_clock = NULL;

    public function test_it_is_initialisable()
    {
        $this->assertInstanceOf(OperationTimer::class, $this->newSubject());
    }

    /**
     * @testWith [false]
     *           [true]
     *           ["I'm a string"]
     *
     * @param mixed $callback
     */
    public function test_timeOperation_calls_callback($callback)
    {
        $subject = $this->newSubject();
        $this->assertSame(
            $callback,
            $subject->timeOperation(
                function () use ($callback) { return $callback; },
                'default_name',
                'default_source'
            )
        );
    }

    public function test_timeOperation_creates_metric()
    {
        $subject = $this->newSubject();
        $metric  = $subject->timeOperation(
            function (MetricId $metric) {
                $this->assertMetricMatches('queries', 'mysql', $metric);
                $metric->setName('queries.failed');
                $metric->setSource('mysql.slave');

                return $metric;
            },
            'queries',
            'mysql'
        );
        $this->assertMetricMatches('queries.failed', 'mysql.slave', $metric);
    }

    /**
     * @testWith ["", ""]
     *           [null, null]
     *           ["foo", null]
     *           [null, "bar"]
     **/
    public function test_timeOperation_throws_if_no_metric_name_or_source($name, $src)
    {
        $subject = $this->newSubject();
        $this->expectException(InvalidArgumentException::class);
        $subject->timeOperation(function () { }, $name, $src);
    }

    public function test_timeOperation_times_operation()
    {
        $this->real_time_clock = StoppedMockClock::atNow();
        $subject               = $this->newSubject();
        $clock                 = $this->real_time_clock;
        $metric                = MetricId::nameAndSource('test', 'test-source');

        $subject->timeOperation(
            function () use ($clock) { $clock->tickMicroseconds(250 * 1000); },
            $metric->getName(),
            $metric->getSource()
        );
        AssertMetrics::assertTimerValues($this->metrics->getMetrics(), $metric, [250]);
    }

    public function test_times_operation_on_child_exception()
    {
        $this->real_time_clock = StoppedMockClock::atNow();
        $subject               = $this->newSubject();
        $clock                 = $this->real_time_clock;
        $metric                = MetricId::nameAndSource('default_name', 'default_source');

        $e = new InvalidArgumentException('testing');
        try {
            $subject->timeOperation(
                function () use ($clock, $e) {
                    $clock->tickMicroseconds(500 * 1000);
                    throw $e;
                },
                $metric->getName(),
                $metric->getSource()
            );

        } catch (InvalidArgumentException $got_e) {
            $this->assertSame($e, $got_e, 'sanity check in case of something else');
        }
        AssertMetrics::assertTimerValues($this->metrics->getMetrics(), $metric, [500]);
    }

    private function assertMetricMatches(string $expect_name, string $expect_source, MetricId $metric)
    {
        $this->assertSame($expect_name, $metric->getName());
        $this->assertSame($expect_source, $metric->getSource());
    }

    protected function setUp(): void
    {
        $this->metrics = new ArrayMetricsAgent();
        parent::setUp();
    }

    private function newSubject(): OperationTimer
    {
        return new OperationTimer($this->metrics, $this->real_time_clock);
    }

}
