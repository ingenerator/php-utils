<?php
/**
 * @author    Craig Gosman <craig@ingenerator.com>
 * @licence   BSD-3-Clause
 */


namespace Ingenerator\PHPUtils\Monitoring;


use DateTimeImmutable;

interface MetricsAgent
{
    /**
     * For recording elapsed times of execution
     */
    public function addTimer(MetricId $metric, DateTimeImmutable $start_time, DateTimeImmutable $end_time): void;

    /**
     * Similar to a timer but for recording values which are not time based but where min, max, mean are useful
     */
    public function addSample(MetricId $metric, float $value): void;

    /**
     * Simple counter with single positive increment
     */
    public function incrementCounterByOne(MetricId $metric): void;

    /**
     * Record arbitrary point-in-time values - e.g. a cache hit rate - similar to a key-value store,
     * on most agents only the most recent value is recorded.
     */
    public function setGauge(MetricId $metric, float $value): void;

    /**
     * Record a counter value where you have the current value and want the agent / backend to calculate
     * the differential between each measurement.
     *
     * For example, `total connections since last restart`.
     */
    public function recordCounterIntegral(MetricId $metric, float $value): void;
}
