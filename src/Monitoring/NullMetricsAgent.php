<?php
/**
 * @author    Craig Gosman <craig@ingenerator.com>
 * @licence   BSD-3-Clause
 */


namespace Ingenerator\PHPUtils\Monitoring;


use DateTimeImmutable;

class NullMetricsAgent implements MetricsAgent
{
    public function addTimer(MetricId $metric, DateTimeImmutable $start_time, DateTimeImmutable $end_time): void
    {
        //no-op
    }

    public function addSample(MetricId $metric, float $value): void
    {
        //no-op
    }

    public function incrementCounterByOne(MetricId $metric, int $increment = 1): void
    {
        //no-op
    }

    public function setGauge(MetricId $metric, float $value): void
    {
        //no-op
    }

    public function recordCounterIntegral(MetricId $metric, float $value): void
    {
        //no-op
    }
}
