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

class AssertMetricsTest extends TestCase
{
    public function test_assertNoMetricsCaptured()
    {
        $agent = new ArrayMetricsAgent();
        AssertMetrics::assertNoMetricsCaptured($agent->getMetrics());
        $agent->incrementCounterByOne(new MetricId('duff', 'test'));
        try {
            AssertMetrics::assertNoMetricsCaptured($agent->getMetrics());
        } catch (ExpectationFailedException $e) {
            $this->assertStringContainsString('Expected no metrics', $e->getMessage());
        }
    }

    public function test_assertNoMetricsFor()
    {
        $agent = new ArrayMetricsAgent();
        $agent->incrementCounterByOne(new MetricId('foo', 'bar'));
        $agent->setGauge(new MetricId('foo', 'bar'), 10.9);
        AssertMetrics::assertNoMetricsFor($agent->getMetrics(), 'bar');
        try {
            AssertMetrics::assertNoMetricsFor($agent->getMetrics(), 'foo');
        } catch (ExpectationFailedException $e) {
            $this->assertStringContainsString('Expected no metrics with name "foo"', $e->getMessage());
        }
    }

    public function test_it_can_assert_only_one_metric_recorded()
    {
        $agent = new ArrayMetricsAgent();
        $start = new DateTimeImmutable();
        $end   = new DateTimeImmutable();
        $agent->addTimer(new MetricId('queries', 'mysql'), $start, $end);
        AssertMetrics::assertCapturedOneTimer($agent->getMetrics(), 'queries', 'mysql');
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
        $agent = new ArrayMetricsAgent();
        foreach ($metrics as $metric) {
            $agent->addTimer(
                new MetricId($metric['name'], $metric['source']),
                new DateTimeImmutable,
                new DateTimeImmutable
            );
        }
        try {
            AssertMetrics::assertCapturedOneTimer($agent->getMetrics(), 'foo', 'bar', 'custom msg');
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
        $agent  = new ArrayMetricsAgent();
        $metric = new MetricId('timer', 'test');
        $agent->addTimer($metric, new \DateTimeImmutable($start), new \DateTimeImmutable($end));
        $this->assertAssertionResult(
            $expect_success,
            fn() => AssertMetrics::assertTimerValues($agent->getMetrics(), $metric, $expected_times, $tolerance)
        );
    }

    /**
     * @testWith ["something", "test", true]
     *           ["something_else", "test", false]
     *           ["something", "another_system", false]
     */
    public function test_assert_counter_increments_asserts_correct_metric(string $name, string $source, bool $success)
    {
        $agent = new ArrayMetricsAgent();
        $agent->incrementCounterByOne(new MetricId($name, $source));
        $this->assertAssertionResult(
            $success,
            fn() => AssertMetrics::assertCounterIncrementsByOne($agent->getMetrics(), new MetricId('something', 'test'))
        );
    }

    public function test_assert_counter_increments_fails_if_same_metric_recorded_more_than_once()
    {
        $agent  = new ArrayMetricsAgent();
        $metric = new MetricId('anything', 'somewhere');
        $agent->incrementCounterByOne($metric);
        $agent->incrementCounterByOne($metric);

        $this->assertAssertionFails(
            fn() => AssertMetrics::assertCounterIncrementsByOne($agent->getMetrics(), $metric)
        );
    }

    /**
     * @testWith ["something", "test", 15.5, true]
     *           ["something", "test", 18, false]
     *           ["something_else", "test", 15.5, false]
     *           ["something", "another_system", 15.5, false]
     */
    public function test_assert_sample_asserts_correct_metric(string $name, string $source, float $value, bool $success)
    {
        $agent = new ArrayMetricsAgent();
        $agent->addSample(new MetricId($name, $source), $value);
        $this->assertAssertionResult(
            $success,
            fn() => AssertMetrics::assertSample($agent->getMetrics(), new MetricId('something', 'test'), 15.5)
        );
    }

    /**
     * @testWith [[1.2, 1.2], 1.2, false]
     *           [[1.2, 2.3], 2.3, false]
     *           [[1.2], 1.2, true]
     */
    public function test_assert_sample_fails_if_same_metric_recorded_more_than_once(
        array $values,
        float $assert_value,
        bool $success
    ) {
        $agent  = new ArrayMetricsAgent();
        $metric = new MetricId('anything', 'somewhere');
        foreach ($values as $value) {
            $agent->addSample($metric, $value);
        }

        $this->assertassertionresult(
            $success,
            fn() => AssertMetrics::assertSample($agent->getMetrics(), $metric, $assert_value)
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
        $agent = new ArrayMetricsAgent();
        $agent->setGauge(new MetricId($name, $source), $value);
        $this->assertAssertionResult(
            $success,
            fn() => AssertMetrics::assertGauge($agent->getMetrics(), new MetricId('something', 'test'), 15.5)
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
        $agent  = new ArrayMetricsAgent();
        $metric = new MetricId('anything', 'somewhere');
        foreach ($values as $value) {
            $agent->setGauge($metric, $value);
        }

        $this->assertassertionresult(
            $success,
            fn() => AssertMetrics::assertGauge($agent->getMetrics(), $metric, $assert_value)
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
        $agent  = new ArrayMetricsAgent();
        $metric = new MetricId($m['name'], $m['source']);
        $agent->setGauge($metric, 2);
        $agent->incrementCounterByOne(new MetricId('same', 'same'));
        $this->assertassertionresult($success, fn() => AssertMetrics::assertGauge($agent->getMetrics(), $metric, 2));
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

}
