<?php
/**
 * @author    Craig Gosman <craig@ingenerator.com>
 * @licence   BSD-3-Clause
 */


namespace Ingenerator\PHPUtils\Monitoring;


use DateTimeImmutable;

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
}
