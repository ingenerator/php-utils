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

final class StoppedMockClock extends RealtimeClock
{
    private float $current_microtime;

    /** @var list<int> */
    private array $sleeps = [];

    /**
     * Use descriptive static constructors for a new instance.
     */
    protected function __construct(DateTimeImmutable $start_time)
    {
        $this->current_microtime = (float) $start_time->format('U.u');
    }

    public static function atMicrotime(float $microtime): static
    {
        return new self(DateTimeImmutableFactory::atMicrotime($microtime));
    }

    public static function atNow(): static
    {
        return new self(new DateTimeImmutable());
    }

    public static function at(string|DateTimeImmutable $time): static
    {
        if (!$time instanceof DateTimeImmutable) {
            $time = new DateTimeImmutable($time);
        }

        return new self($time);
    }

    public static function atTimeAgo(string $interval_spec): static
    {
        $now = new DateTimeImmutable();
        $ago = $now->sub(new DateInterval($interval_spec));

        return new self($ago);
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

    public function tickMicroseconds(float $microseconds): void
    {
        $this->current_microtime += ($microseconds / 1000000);
    }

    public function usleep(int $microseconds): void
    {
        $this->tickMicroseconds($microseconds);
        $this->sleeps[] = $microseconds;
    }

    /** @param list<int> $expected */
    public function assertSlept(array $expected, string $msg = ''): void
    {
        Assert::assertSame($expected, $this->sleeps, $msg);
    }

    public function assertNeverSlept(string $msg = ''): void
    {
        Assert::assertSame([], $this->sleeps, $msg);
    }
}
