<?php
/**
 * @author    Craig Gosman <craig@ingenerator.com>
 * @licence   BSD-3-Clause
 */


namespace Ingenerator\PHPUtils\Monitoring;


use DateTimeImmutable;

interface MetricsAgent
{
    public function addTimer(MetricId $metric, DateTimeImmutable $start_time, DateTimeImmutable $end_time): void;

    public function addSample(MetricId $metric, float $value): void;

    public function incrementCounterByOne(MetricId $metric): void;

    public function setGauge(MetricId $metric, float $value): void;

    public function recordCounterIntegral(MetricId $metric, float $value): void;
}
