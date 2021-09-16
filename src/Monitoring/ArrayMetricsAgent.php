<?php
/**
 * @author    Craig Gosman <craig@ingenerator.com>
 * @licence   BSD-3-Clause
 */


namespace Ingenerator\PHPUtils\Monitoring;


use DateTimeImmutable;

class ArrayMetricsAgent implements MetricsAgent
{
    protected array $metrics = [];

    public function addTimer(MetricId $metric, DateTimeImmutable $start_time, DateTimeImmutable $end_time): void
    {
        $this->metrics[] = [
            'type'    => 'timer',
            'name'    => $metric->getName(),
            'source'  => $metric->getSource(),
            'payload' => ['start' => $start_time, 'end' => $end_time],
        ];
    }

    public function getMetrics(): array
    {
        return $this->metrics;
    }
}
