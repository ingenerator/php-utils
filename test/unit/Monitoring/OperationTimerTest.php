<?php
/**
 * @author    Craig Gosman <craig@ingenerator.com>
 * @licence   proprietary
 */


namespace test\unit\Ingenerator\PHPUtils\unit\Monitoring;


use DateTimeImmutable;
use Ingenerator\PHPUtils\DateTime\Clock\RealtimeClock;
use Ingenerator\PHPUtils\DateTime\Clock\StoppedMockClock;
use Ingenerator\PHPUtils\Monitoring\ArrayMetricsAgent;
use Ingenerator\PHPUtils\Monitoring\MetricId;
use Ingenerator\PHPUtils\Monitoring\OperationTimer;
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
     *              [null, null]
     *              ["foo", null]
     *              [null, "bar"]
     **/
    public function test_timeOperation_throws_if_no_metric_name_or_source($name, $src)
    {
        $subject = $this->newSubject();
        $this->expectException(\InvalidArgumentException::class);
        $subject->timeOperation(function () { }, $name, $src);
    }

    public function test_timeOperation_times_operation()
    {
        $this->real_time_clock = StoppedMockClock::atNow();
        $subject               = $this->newSubject();
        $clock                 = $this->real_time_clock;

        $subject->timeOperation(
            function () use ($clock) { $clock->tickMicroseconds(250 * 1000); },
            'test',
            'test-source'
        );
        $this->assertTimerMilliseconds(250);
    }

    public function test_times_operation_on_child_exception()
    {
        $this->real_time_clock = StoppedMockClock::atNow();
        $subject               = $this->newSubject();
        $clock                 = $this->real_time_clock;

        $e = new \InvalidArgumentException('testing');
        try {
            $subject->timeOperation(
                function () use ($clock, $e) {
                    $clock->tickMicroseconds(500 * 1000);
                    throw $e;
                },
                'default_name',
                'default_source'
            );

        } catch (\InvalidArgumentException $got_e) {
            $this->assertSame($e, $got_e, 'sanity check in case of something else');
        }

        $this->assertTimerMilliseconds(500);
    }

    private function assertMetricMatches(string $expect_name, string $expect_source, MetricId $metric)
    {
        $this->assertSame($expect_name, $metric->getName());
        $this->assertSame($expect_source, $metric->getSource());
    }

    private function assertTimerMilliseconds(int $expected_time_millis)
    {
        $metric = $this->metrics->getTimers()[0];
        $start  = $metric['start'];
        /** @var $start DateTimeImmutable */
        $end = $metric['end'];
        /** @var $end DateTimeImmutable */
        $this->assertSame(
            $expected_time_millis / 1000,
            (float) $end->format('U.u') - (float) $start->format('U.u')
        );
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
