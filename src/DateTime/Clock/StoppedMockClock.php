<?php

/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 *
 * @licence   BSD-3-Clause
 */

namespace Ingenerator\PHPUtils\DateTime\Clock;

use DateInterval;
use DateTimeImmutable;
use Ingenerator\PHPUtils\DateTime\DateTimeImmutableFactory;
use PHPUnit\Framework\Assert;

class StoppedMockClock extends RealtimeClock
{
    /**
     * @var float
     */
    protected $current_microtime;

    /**
     * @var int[]
     */
    protected $sleeps;

    /**
     * Use descriptive static constructors for a new instance.
     */
    protected function __construct(DateTimeImmutable $start_time)
    {
        $this->current_microtime = (float) $start_time->format('U.u');
    }

    /**
     * @param float $microtime
     */
    public static function atMicrotime($microtime): static
    {
        return new static(DateTimeImmutableFactory::atMicrotime($microtime));
    }

    public static function atNow(): static
    {
        return new static(new DateTimeImmutable());
    }

    /**
     * @param string $time
     */
    public static function at($time): static
    {
        if (!$time instanceof DateTimeImmutable) {
            $time = new DateTimeImmutable($time);
        }

        return new static($time);
    }

    /**
     * @param string $interval_spec
     */
    public static function atTimeAgo($interval_spec): static
    {
        $now = new DateTimeImmutable();
        $ago = $now->sub(new DateInterval($interval_spec));

        return new static($ago);
    }

    public function getDateTime(): DateTimeImmutable
    {
        return DateTimeImmutableFactory::atMicrotime($this->current_microtime);
    }

    public function getMicrotime(): float
    {
        return $this->current_microtime;
    }

    public function tick(DateInterval $period): void
    {
        $now = $this->getDateTime()->add($period);
        $this->current_microtime = (float) $now->getTimestamp();
    }

    /**
     * @param float $microseconds
     */
    public function tickMicroseconds($microseconds): void
    {
        $this->current_microtime += ($microseconds / 1000000);
    }

    public function usleep($microseconds): void
    {
        $this->tickMicroseconds($microseconds);
        $this->sleeps[] = $microseconds;
    }

    public function assertSlept(array $expected, $msg = ''): void
    {
        Assert::assertSame($expected, $this->sleeps, $msg);
    }

    public function assertNeverSlept($msg = ''): void
    {
        Assert::assertNull($this->sleeps, $msg);
    }
}
