<?php

namespace test\unit\Ingenerator\PHPUtils\unit\DateTime\Clock;

use Closure;
use DateInterval;
use DateTimeImmutable;
use Ingenerator\PHPUtils\DateTime\Clock\StoppedMockClock;
use Ingenerator\PHPUtils\DateTime\DateIntervalFactory;
use Ingenerator\PHPUtils\DateTime\DateString;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;

use function microtime;
use function round;
use function sleep;

class StoppedMockClockTest extends TestCase
{
    public function testItIsInitialisableNow(): void
    {
        $clock = StoppedMockClock::atNow();
        $this->assertEqualsWithDelta(
            new DateTimeImmutable(),
            $clock->getDateTime(),
            1,
            'Starts at the right time'
        );
    }

    public static function provider_at_fixed(): array
    {
        return [
            [
                '2019-03-04 10:02:03',
                new DateTimeImmutable('2019-03-04 10:02:03'),
                1551693723.0,
            ],
            [
                new DateTimeImmutable('2019-03-04 10:02:03'),
                new DateTimeImmutable('2019-03-04 10:02:03'),
                1551693723.0,
            ],
            [
                new DateTimeImmutable('2019-03-04 10:02:03.248123'),
                new DateTimeImmutable('2019-03-04 10:02:03.248123'),
                1551693723.248123,
            ],
        ];
    }

    #[DataProvider('provider_at_fixed')]
    public function testItIsInitialisableAtFixedTimeFromStringOrObject(string|DateTimeImmutable $at_what, DateTimeImmutable $expect_time, float $expect_micro): void
    {
        $clock = StoppedMockClock::at($at_what);
        $this->assertEquals($expect_time, $clock->getDateTime());
        $this->assertSame($expect_micro, $clock->getMicrotime());
    }

    public function testItIsInitialisableAtFixedMicrotime(): void
    {
        $clock = StoppedMockClock::atMicrotime(1551693723.1239);
        $this->assertSame(1551693723.1239, $clock->getMicrotime());
        $this->assertSame('2019-03-04 10:02:03.123900', $clock->getDateTime()->format('Y-m-d H:i:s.u'));
    }

    public function testItIsInitialisableAtADateIntervalInThePast(): void
    {
        $clock = StoppedMockClock::atTimeAgo('P3D');
        $ago = (new DateTimeImmutable())->sub(new DateInterval('P3D'));
        $this->assertEqualsWithDelta($ago, $clock->getDateTime(), 1, 'Time is at correct interval');
    }

    public function testItHoldsItsTimeForeverInRealLife(): void
    {
        $clock = StoppedMockClock::atNow();
        $start_microtime = $clock->getMicrotime();
        $start_time = $clock->getDateTime();
        sleep(2);
        $this->assertEquals($start_time, $clock->getDateTime(), 'Stays at the same time');
        $this->assertSame($start_microtime, $clock->getMicrotime(), 'Stays at the same microtime');
    }

    public function testItAdvancesTimeAfterEachTick(): void
    {
        $clock = StoppedMockClock::at('2019-01-05 10:03:02');
        $this->assertSame(1546682582.0, $clock->getMicrotime(), 'Correct starting microtime');

        $clock->tick(new DateInterval('P1D'));
        $this->assertEquals(new DateTimeImmutable('2019-01-06 10:03:02'), $clock->getDateTime());
        $this->assertSame(1546768982.0, $clock->getMicrotime());
    }

    public function testItAdvancesTimeAfterEachTickMicroseconds(): void
    {
        $clock = StoppedMockClock::atMicrotime(1546682582.150);
        $this->assertEquals(new DateTimeImmutable('2019-01-05 10:03:02.150'), $clock->getDateTime());

        $clock->tickMicroseconds(150000);
        $this->assertSame(1546682582.300, round($clock->getMicrotime(), 3));
        $this->assertEquals(new DateTimeImmutable('2019-01-05 10:03:02.300'), $clock->getDateTime(), 'DateTime not changed by sub-second tick');

        $clock->tickMicroseconds(750000);
        $this->assertSame(1546682583.050, round($clock->getMicrotime(), 3));
        $this->assertEquals(new DateTimeImmutable('2019-01-05 10:03:03.050'), $clock->getDateTime(), 'DateTime changed after second boundary');
    }

    public function testItsUsleepIsImmediateButAdvancesTime(): void
    {
        $clock = StoppedMockClock::atMicrotime(1546682582.05);
        $start = microtime(true);
        $clock->usleep(900000);
        $real_ms = 1000 * (microtime(true) - $start);
        $this->assertLessThan(50, $real_ms, 'Should not actually sleep');
        $this->assertSame(1546682582.95, round($clock->getMicrotime(), 3), 'Should update time');
    }

    public static function provider_assert_slept_fails(): array
    {
        return [
            [
                function (): void {
                },
                [15],
                'Never slept at all',
            ],
            [
                function (StoppedMockClock $clock): void {
                    $clock->usleep(150);
                },
                [15],
                'Wrong amount of sleep',
            ],
            [
                function (StoppedMockClock $clock): void {
                    $clock->usleep(150);
                },
                [150, 30],
                'Incorrect number of sleeps',
            ],
            [
                function (StoppedMockClock $clock): void {
                    $clock->usleep(150);
                    $clock->usleep(10);
                },
                [10, 150],
                'Sleeps in wrong order',
            ],
        ];
    }

    #[DataProvider('provider_assert_slept_fails')]
    public function testAssertSleptFailsIfNotSleptForExpectedIntervals(Closure $callback, array $expected, string $msg): void
    {
        $clock = StoppedMockClock::atNow();
        $callback($clock);
        $this->expectException(ExpectationFailedException::class);
        $clock->assertSlept($expected, $msg);
    }

    public function testAssertSleptPassesIfSleptForExpectedIntervals(): void
    {
        $clock = StoppedMockClock::atNow();
        $clock->usleep(50000);
        $clock->usleep(20000);
        $clock->usleep(15000);
        $this->assertNull(
            $clock->assertSlept([50000, 20000, 15000])
        );
    }

    public function testAssertNeverSleptPassesIfNeverSlept(): void
    {
        $clock = StoppedMockClock::atNow();
        $this->assertNull($clock->assertNeverSlept());
    }

    public function testAssertNeverSleptIfEverSlept(): void
    {
        $clock = StoppedMockClock::atNow();
        $clock->usleep(500);
        $this->expectException(ExpectationFailedException::class);
        $clock->assertNeverSlept();
    }

    public function testAgoAndFutureWorkAsExpected(): void
    {
        $clock = StoppedMockClock::at('2024-11-14 13:56:20.203123');
        $this->assertSame(
            [
                'ago_with_time' => '2023-11-14T13:56:20.203123+00:00',
                'ago_date_only' => '2023-11-14T00:00:00.000000+00:00',
                'future_with_time' => '2025-01-14T13:56:20.203123+00:00',
                'future_date_only' => '2025-01-14T00:00:00.000000+00:00',
            ],
            array_map(
                DateString::isoMS(...),
                [
                    'ago_with_time' => $clock->ago(DateIntervalFactory::years(1)),
                    'ago_date_only' => $clock->ago(DateIntervalFactory::years(1), date_only: true),
                    'future_with_time' => $clock->future(DateIntervalFactory::months(2)),
                    'future_date_only' => $clock->future(DateIntervalFactory::months(2), date_only: true),
                ]
            )
        );
    }
}
