<?php
/**
 * @author    Craig Gosman <craig@ingenerator.com>
 * @licence   BSD-3-Clause
 */

namespace Ingenerator\PHPUtils\Monitoring;

use Ingenerator\PHPUtils\DateTime\DateTimeDiff;
use PHPUnit\Framework\Assert;
use function array_filter;
use function array_map;
use function array_values;
use function count;

/**
 * Intended to be used exclusively to assert metrics captured by ArrayMetricsAgent
 */
class AssertMetrics
{
    private static function findMetrics(array $metrics, MetricId $metric): array
    {
        return array_values(
            array_filter(
                $metrics,
                fn(array $m) => ($m['name'] === $metric->getName() and $m['source'] === $metric->getSource())
            )
        );
    }

    public static function assertCapturedOneTimer(
        array $metrics,
        string $name,
        ?string $source = NULL,
        ?string $msg = NULL
    ): void {
        Assert::assertEquals(
            [['name' => $name, 'source' => $source]],
            array_map(
                fn(array $m) => ['name' => $m['name'], 'source' => $m['source']],
                $metrics
            ),
            $msg ?? 'Expected exactly one timer matching the expectation'
        );
    }

    private static function assertOnlyOneMetricTypeAndPayload(array $metrics, string $type, $expected_payload)
    {
        Assert::assertCount(1, $metrics, 'Expected only 1 metric recorded');
        Assert::assertSame($type, $metrics[0]['type'], 'Expected metric to be of type '.$type);
        Assert::assertSame($expected_payload, $metrics[0]['payload'], 'Expected metric value to be '.$expected_payload);
    }

    public static function assertSample(array $metrics, MetricId $metric, float $expected_value): void
    {
        $filtered = self::findMetrics($metrics, $metric);
        self::assertOnlyOneMetricTypeAndPayload($filtered, 'sample', $expected_value);
    }

    public static function assertGauge(array $metrics, MetricId $metric, float $expected_value): void
    {
        $filtered = self::findMetrics($metrics, $metric);
        self::assertOnlyOneMetricTypeAndPayload($filtered, 'gauge', $expected_value);
    }

    public static function assertCounterIncrementsByOne(array $metrics, MetricId $metric): void
    {
        $filtered = self::findMetrics($metrics, $metric);
        self::assertOnlyOneMetricTypeAndPayload($filtered, 'counter', 1);
    }

    public static function assertNoMetricsCaptured(array $metrics): void
    {
        Assert::assertEmpty($metrics, "Expected no metrics to be captured");
    }

    public static function assertNoMetricsFor(array $metrics, string $metric_name)
    {
        Assert::assertNotContains(
            $metric_name,
            array_map(fn($m) => $m['name'], $metrics),
            'Expected no metrics with name "'.$metric_name.'"'
        );
    }

    public static function assertTimerValues(
        array $metrics,
        MetricId $metric,
        array $expected_times,
        float $tolerance_ms = 0
    ) {
        $filtered_metrics = array_values(
            array_filter($metrics,
                fn($m) => ($m['type'] === 'timer' and
                    $m['name'] === $metric->getName() and
                    $m['source'] === $metric->getSource())
            )
        );

        Assert::assertCount(count($expected_times), $filtered_metrics);

        foreach ($filtered_metrics as $index => $actual) {
            Assert::assertEqualsWithDelta(
                $expected_times[$index],
                DateTimeDiff::microsBetween($actual['payload']['start'], $actual['payload']['end']) / 1000,
                $tolerance_ms,
                'Time #'.$index.' should be '.$expected_times[$index].' +/- '.$tolerance_ms.'ms',
            );
        }
    }
}
