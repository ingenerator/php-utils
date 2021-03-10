<?php
/**
 * @author    Craig Gosman <craig@ingenerator.com>
 * @licence   proprietary
 */


namespace Ingenerator\PHPUtils\Monitoring;


use DateTimeImmutable;
use PHPUnit\Framework\Assert;

class ArrayMetricsAgent implements MetricsAgent
{
    protected array $timers = [];

    public function addTimer(MetricId $metric, DateTimeImmutable $start_time, DateTimeImmutable $end_time): void
    {
        $this->timers[] = [
            'name'   => $metric->getName(),
            'source' => $metric->getSource(),
            'start'  => $start_time,
            'end'    => $end_time,
        ];
    }

    public function assertCapturedOneTimer(string $name, ?string $source = NULL, ?string $msg = NULL): void
    {
        Assert::assertEquals(
            [['name' => $name, 'source' => $source]],
            array_map(
                fn(array $m) => ['name' => $m['name'], 'source' => $m['source']],
                $this->timers
            ),
            $msg ?? 'Expected exactly one timer matching the expectation'
        );
    }

    public function getTimers(): array
    {
        return $this->timers;
    }
}
