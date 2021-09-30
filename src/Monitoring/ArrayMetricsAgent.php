<?php
/**
 * @author    Craig Gosman <craig@ingenerator.com>
 * @licence   BSD-3-Clause
 */


namespace Ingenerator\PHPUtils\Monitoring;


use DateTimeImmutable;
use function array_filter;
use function array_values;

class ArrayMetricsAgent implements MetricsAgent
{
    private array $metrics = [];

    public function getMetrics(): array
    {
        return $this->metrics;
    }

    public function addTimer(MetricId $metric, DateTimeImmutable $start_time, DateTimeImmutable $end_time): void
    {
        $this->pushMetric('timer', $metric, ['start' => $start_time, 'end' => $end_time]);
    }

    public function addSample(MetricId $metric, float $value): void
    {
        $this->pushMetric('sample', $metric, $value);
    }

    public function incrementCounterByOne(MetricId $metric): void
    {
        $this->pushMetric('counter', $metric, 1);
    }

    public function setGauge(MetricId $metric, float $value): void
    {
        $this->pushMetric('gauge', $metric, $value);
    }

    public function recordCounterIntegral(MetricId $metric, float $value): void
    {
        $this->pushMetric('counter-integral', $metric, $value);
    }

    private function pushMetric(string $type, MetricId $metric, $payload)
    {
        $this->metrics[] = [
            'type'    => $type,
            'name'    => $metric->getName(),
            'source'  => $metric->getSource(),
            'payload' => $payload,
        ];
    }

    /**
     * @deprecated use AssertMetrics::assertCapturedOneTimer
     */
    public function assertCapturedOneTimer(string $name, ?string $source = NULL, ?string $msg = NULL): void
    {
        AssertMetrics::assertCapturedOneTimer($this->metrics, $name, $source, $msg);
    }

    /**
     * @deprecated
     */
    public function getTimers(): array
    {
        $all_timers = array_values(array_filter($this->metrics, fn(array $m) => ($m['type'] === 'timer')));

        return array_map(
            fn(array $m) => ([
                'name'   => $m['name'],
                'source' => $m['source'],
                'start'  => $m['payload']['start'],
                'end'    => $m['payload']['end'],
            ]),
            $all_timers
        );
    }
}
