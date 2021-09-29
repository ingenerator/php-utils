<?php
/**
 * @author    Craig Gosman <craig@ingenerator.com>
 * @licence   BSD-3-Clause
 */


namespace test\unit\Ingenerator\PHPUtils\unit\Monitoring;


use DateTimeImmutable;
use Ingenerator\PHPUtils\Monitoring\ArrayMetricsAgent;
use Ingenerator\PHPUtils\Monitoring\AssertMetrics;
use Ingenerator\PHPUtils\Monitoring\MetricId;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;

class ArrayMetricsAgentTest extends TestCase
{
    public function test_it_is_initialisable()
    {
        $this->assertInstanceOf(ArrayMetricsAgent::class, $this->newSubject());
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

    public function test_assertNoMetricsCaptured()
    {
        $subject = $this->newSubject();
        AssertMetrics::assertNoMetricsCaptured($subject->getMetrics());
        $subject->incrementCounterByOne(new MetricId('duff', 'test'));
        try {
            AssertMetrics::assertNoMetricsCaptured($subject->getMetrics());
        } catch (ExpectationFailedException $e) {
            $this->assertStringContainsString('Expected no metrics', $e->getMessage());
        }
    }

    public function test_assertNoMetricsFor()
    {
        $subject = $this->newSubject();
        $subject->incrementCounterByOne(new MetricId('foo', 'bar'));
        $subject->setGauge(new MetricId('foo', 'bar'), 10.9);
        AssertMetrics::assertNoMetricsFor($subject->getMetrics(), 'bar');
        try {
            AssertMetrics::assertNoMetricsFor($subject->getMetrics(), 'foo');
        } catch (ExpectationFailedException $e) {
            $this->assertStringContainsString('Expected no metrics with name "foo"', $e->getMessage());
        }
    }

    public function test_it_can_assert_only_one_metric_recorded()
    {
        $subject = $this->newSubject();
        $start   = new DateTimeImmutable();
        $end     = new DateTimeImmutable();
        $subject->addTimer(new MetricId('queries', 'mysql'), $start, $end);
        AssertMetrics::assertCapturedOneTimer($subject->getMetrics(), 'queries', 'mysql');
        $this->addToAssertionCount(1);
    }

    /**
     * @testWith [[], "no metrics"]
     *           [[{"name": "foo", "source": "wrong"}], "wrong source"]
     *           [[{"name": "wrong", "source": "bar"}], "wrong name"]
     *           [[{"name": "foo", "source": "bar"}, {"name": "foo", "source": "bar"}], "duplicate metric"]
     *           [[{"name": "foo", "source": "bar"}, {"name": "foo", "source": "other"}], "extra metric"]
     **/
    public function test_assertCapturedOneTimer_fails_if_no_match(array $metrics)
    {
        $subject = $this->newSubject();
        foreach ($metrics as $metric) {
            $subject->addTimer(
                new MetricId($metric['name'], $metric['source']),
                new DateTimeImmutable,
                new DateTimeImmutable
            );
        }
        try {
            AssertMetrics::assertCapturedOneTimer($subject->getMetrics(), 'foo', 'bar', 'custom msg');
            $this->fail('Expected assertion failure');
        } catch (ExpectationFailedException $e) {
            $this->assertStringContainsString('custom msg', $e->getMessage());
        }
    }

    /**
     * @testWith ["2020-02-02 02:02:02.123456", "2020-02-02 02:02:02.123456", 0, [0], true]
     *           ["2020-02-02 02:02:02.123456", "2020-02-02 02:02:02.123456", 0, [0.001], false]
     *           ["2020-02-02 02:02:02.123456", "2020-02-02 02:02:02.123457", 0, [0.001], true]
     *           ["2020-02-02 02:02:02.123456", "2020-02-02 02:02:02.123455", 0.001, [0.001], false]
     *           ["2020-02-02 02:02:02.123456", "2020-02-02 02:02:02.123456", 0.001, [0.001], true]
     *           ["2020-02-02 02:02:02.123456", "2020-02-02 02:02:02.123458", 0.001, [0.001], true]
     *           ["2020-02-02 02:02:02.123456", "2020-02-02 02:02:02.123459", 0.001, [0.001], false]
     */
    public function test_assertTimerValues(
        string $start,
        string $end,
        float $tolerance,
        array $expected_times,
        bool $expect_success
    ) {
        $subject = $this->newSubject();
        $metric  = new MetricId('timer', 'test');
        $subject->addTimer($metric, new \DateTimeImmutable($start), new \DateTimeImmutable($end));
        $this->assertAssertionResult(
            $expect_success,
            fn() => AssertMetrics::assertTimerValues($subject->getMetrics(), $metric, $expected_times, $tolerance)
        );
    }

    /**
     * @testWith ["something", "test", true]
     *           ["something_else", "test", false]
     *           ["something", "another_system", false]
     */
    public function test_assert_counter_increments_asserts_correct_metric(string $name, string $source, bool $success)
    {
        $subject = $this->newSubject();
        $subject->incrementCounterByOne(new MetricId($name, $source));
        $this->assertAssertionResult(
            $success,
            fn() => AssertMetrics::assertCounterIncrementsByOne($subject->getMetrics(), new MetricId('something', 'test'))
        );
    }

    public function test_assert_counter_increments_fails_if_same_metric_recorded_more_than_once() {
        $subject = $this->newSubject();
        $metric  = new MetricId('anything', 'somewhere');
        $subject->incrementCounterByOne($metric);
        $subject->incrementCounterByOne($metric);

        $this->assertAssertionFails(
            fn() => AssertMetrics::assertCounterIncrementsByOne($subject->getMetrics(), $metric)
        );
    }

    /**
     * @testWith ["something", "test", 15.5, true]
     *           ["something", "test", 18, false]
     *           ["something_else", "test", 15.5, false]
     *           ["something", "another_system", 15.5, false]
     */
    public function test_assert_gauge_asserts_correct_metric(string $name, string $source, float $value, bool $success)
    {
        $subject = $this->newSubject();
        $subject->setGauge(new MetricId($name, $source), $value);
        $this->assertAssertionResult(
            $success,
            fn() => AssertMetrics::assertGauge($subject->getMetrics(), new MetricId('something', 'test'), 15.5)
        );
    }

    /**
     * @testWith [[1.2, 1.2], 1.2, false]
     *           [[1.2, 2.3], 2.3, false]
     *           [[1.2], 1.2, true]
     */
    public function test_assert_gauge_fails_if_same_metric_recorded_more_than_once(
        array $values,
        float $assert_value,
        bool $success
    ) {
        $subject = $this->newSubject();
        $metric  = new MetricId('anything', 'somewhere');
        foreach ($values as $value) {
            $subject->setGauge($metric, $value);
        }

        $this->assertassertionresult(
            $success,
            fn() => AssertMetrics::assertGauge($subject->getMetrics(), $metric, $assert_value)
        );
    }

    /**
     * @testWith [{"name":"other", "source": "other"}, true]
     *           [{"name":"same", "source": "same"}, false]
     *           [{"name":"same", "source": "other"}, true]
     *           [{"name":"other", "source": "same"}, true]
     */
    public function test_assert_gauge_fails_if_any_other_metric_with_same_name_and_source(array $m, bool $success)
    {
        $subject = $this->newSubject();
        $metric  = new MetricId($m['name'], $m['source']);
        $subject->setGauge($metric, 2);
        $subject->incrementCounterByOne(new MetricId('same', 'same'));
        $this->assertassertionresult($success, fn() => AssertMetrics::assertGauge($subject->getMetrics(), $metric, 2));
    }

    private function assertAssertionPasses(callable $fn)
    {
        try {
            $fn();
            $this->addToAssertionCount(1);
        } catch (ExpectationFailedException $e) {
            $this->fail('Expected assertion to pass, but it failed: '.$e->getMessage());
        }
    }

    private function assertAssertionFails(callable $fn)
    {
        try {
            $fn();
            $this->fail('Expected assertion to fail, but it passed.');
        } catch (ExpectationFailedException $e) {
            $this->addToAssertionCount(1);
        }
    }

    private function assertAssertionResult(bool $expect_success, callable $fn)
    {
        if ($expect_success) {
            $this->assertAssertionPasses($fn);
        } else {
            $this->assertAssertionFails($fn);
        }
    }

    private function newSubject(): ArrayMetricsAgent
    {
        return new ArrayMetricsAgent();
    }
}
