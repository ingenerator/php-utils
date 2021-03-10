<?php
/**
 * @author    Craig Gosman <craig@ingenerator.com>
 * @licence   proprietary
 */


namespace Ingenerator\PHPUtils\Monitoring;


use DateTimeImmutable;

interface MetricsAgent
{
    public function addTimer(MetricId $metric, DateTimeImmutable $start_time, DateTimeImmutable $end_time): void;
}
