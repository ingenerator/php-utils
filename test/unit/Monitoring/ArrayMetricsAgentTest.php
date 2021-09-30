<?php
/**
 * @author    Craig Gosman <craig@ingenerator.com>
 * @licence   BSD-3-Clause
 */


namespace test\unit\Ingenerator\PHPUtils\unit\Monitoring;


use DateTimeImmutable;
use Ingenerator\PHPUtils\Monitoring\ArrayMetricsAgent;
use Ingenerator\PHPUtils\Monitoring\MetricId;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;

class ArrayMetricsAgentTest extends TestCase
{
    public function test_it_is_initialisable()
    {
        $this->assertInstanceOf(ArrayMetricsAgent::class, $this->newSubject());
    }

    public function test_it_can_assert_only_one_timer_recorded()
    {
        $subject = $this->newSubject();
        $start   = new DateTimeImmutable();
        $end     = new DateTimeImmutable();
        $subject->addTimer(new MetricId('queries', 'mysql'), $start, $end);
        $subject->assertCapturedOneTimer('queries', 'mysql');
        $this->addToAssertionCount(1);
    }

    /**
     * @testWith [[], "no metrics"]
     *              [[{"name": "foo", "source": "wrong"}], "wrong source"]
     *              [[{"name": "wrong", "source": "bar"}], "wrong name"]
     *              [[{"name": "foo", "source": "bar"}, {"name": "foo", "source": "bar"}], "duplicate metric"]
     *              [[{"name": "foo", "source": "bar"}, {"name": "foo", "source": "other"}], "extra metric"]
     **/
    public function test_assertCapturedOneTimer_fails_if_no_match(array $metrics)
    {
        $subject = $this->newSubject();
        foreach ($metrics as $metric) {
            $subject->addTimer(
                new MetricId($metric['name'], $metric['source']),
                new \DateTimeImmutable,
                new \DateTimeImmutable
            );
        }
        try {
            $subject->assertCapturedOneTimer('foo', 'bar', 'custom msg');
            $this->fail('Expected assertion failure');
        } catch (ExpectationFailedException $e) {
            $this->assertStringContainsString('custom msg', $e->getMessage());
        }
    }

    public function test_returns_all_timers_captured()
    {
        $subject = $this->newSubject();
        $start   = new DateTimeImmutable();
        $end     = new DateTimeImmutable();
        $subject->addTimer(new MetricId('queries', 'mysql'), $start, $end);
        $subject->addTimer(new MetricId('queries', 'mysql.slave'), $start, $end);
        $this->assertSame(
            [
                ['name' => 'queries', 'source' => 'mysql', 'start' => $start, 'end' => $end],
                ['name' => 'queries', 'source' => 'mysql.slave', 'start' => $start, 'end' => $end],
            ],
            $subject->getTimers()
        );
    }

    public function test_returns_all_metrics_captured()
    {
        $subject = $this->newSubject();

        $start = new DateTimeImmutable();
        $end   = new DateTimeImmutable();

        $subject->addTimer(new MetricId('queries', 'mysql'), $start, $end);
        $subject->addTimer(new MetricId('queries', 'mysql.slave'), $start, $end);
        $subject->incrementCounterByOne(new MetricId('email_sent', 'test'));
        $subject->recordCounterIntegral(new MetricId('opcache-restarts-oom', 'test'), 5);
        $subject->setGauge(new MetricId('number_in_queue', 'test'), 16.5);
        $subject->addSample(new MetricId('mem_used', 'test'), 224.56);
        $this->assertSame(
            [
                [
                    'type'    => 'timer',
                    'name'    => 'queries',
                    'source'  => 'mysql',
                    'payload' => ['start' => $start, 'end' => $end],
                ],
                [
                    'type'    => 'timer',
                    'name'    => 'queries',
                    'source'  => 'mysql.slave',
                    'payload' => ['start' => $start, 'end' => $end],
                ],
                ['type' => 'counter', 'name' => 'email_sent', 'source' => 'test', 'payload' => 1],
                ['type' => 'counter-integral', 'name' => 'opcache-restarts-oom', 'source' => 'test', 'payload' => 5.0],
                ['type' => 'gauge', 'name' => 'number_in_queue', 'source' => 'test', 'payload' => 16.5],
                ['type' => 'sample', 'name' => 'mem_used', 'source' => 'test', 'payload' => 224.56],
            ],
            $subject->getMetrics()
        );
    }

    public function test_it_records_each_counter_increment_by_one()
    {
        $subject = $this->newSubject();
        $subject->incrementCounterByOne(new MetricId('email_sent', 'test'));
        $subject->incrementCounterByOne(new MetricId('email_sent', 'test'));
        $subject->incrementCounterByOne(new MetricId('email_sent', 'test'));
        $this->assertSame(
            [
                ['type' => 'counter', 'name' => 'email_sent', 'source' => 'test', 'payload' => 1],
                ['type' => 'counter', 'name' => 'email_sent', 'source' => 'test', 'payload' => 1],
                ['type' => 'counter', 'name' => 'email_sent', 'source' => 'test', 'payload' => 1],
            ],
            $subject->getMetrics()
        );
    }

    private function newSubject(): ArrayMetricsAgent
    {
        return new ArrayMetricsAgent();
    }
}
