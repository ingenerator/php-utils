<?php
/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 * @licence   BSD-3-Clause
 */

namespace test\unit\Ingenerator\PHPUtils\DateTime\Clock;


use Closure;
use DateTimeImmutable;
use Ingenerator\PHPUtils\DateTime\Clock\RealtimeClock;
use Ingenerator\PHPUtils\DateTime\DateIntervalFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RealtimeClockTest extends TestCase
{

    public function test_it_is_initialisable()
    {
        $this->assertInstanceOf(RealtimeClock::class, $this->newSubject());
    }

    public function test_it_returns_current_time()
    {
        $time = $this->newSubject()->getDateTime();
        $this->assertInstanceOf(DateTimeImmutable::class, $time);
        // Allow for time changing during the test
        $this->assertEqualsWithDelta(
            new DateTimeImmutable,
            $time,
            1,
            'Should be roughly the real time'
        );
    }

    public function test_it_returns_microtime()
    {
        $mt = $this->newSubject()->getMicrotime();
        $this->assertIsFloat($mt);
        $this->assertEqualsWithDelta(
            \microtime(TRUE),
            $mt,
            0.2,
            'Should be roughly the real microtime'
        );
    }

    public function test_it_sleeps()
    {
        $start   = \microtime(TRUE);
        $subject = $this->newSubject();
        $subject->usleep(500);
        $end = \microtime(TRUE);
        $slept_for = $end - $start;
        $this->assertGreaterThanOrEqual(0.0005, $slept_for, 'Should always sleep at least the minimum time');
        $this->assertLessThan(0.004, $slept_for, 'May sleep a bit longer if running slow');
    }

    /**
     * @slowThreshold 3000
     */
    public function test_time_continues_during_the_life_of_an_instance()
    {
        $subject  = $this->newSubject();
        $start_ts = $subject->getDateTime();
        $start_mt = $subject->getMicrotime();

        \usleep(2500000);

        $end_ts = $subject->getDateTime();
        $end_mt = $subject->getMicrotime();

        $this->assertEqualsWithDelta(
            2,
            $end_ts->getTimestamp() - $start_ts->getTimestamp(),
            1,
            'Seconds move'
        );
        $this->assertEqualsWithDelta(
            2.5,
            $end_mt - $start_mt,
            0.2,
            'microseconds should be about right'
        );
    }

    public static function provider_relative_times():array
    {
        return [
            'in the past, with time component' => [
                fn(RealtimeClock $clock) => $clock->ago(DateIntervalFactory::years(6)),
                '-6 year',
                false,
            ],
            'in the past, with date only' => [
                fn(RealtimeClock $clock) => $clock->ago(DateIntervalFactory::months(6), date_only: true),
                '-6 months 00:00:00.000000',
                true,
            ],
            'in the future, with time component' => [
                fn(RealtimeClock $clock) => $clock->future(DateIntervalFactory::years(2)),
                '+2 year',
                false,
            ],
            'in the future, with date only' => [
                fn(RealtimeClock $clock) => $clock->future(DateIntervalFactory::months(1), date_only: true),
                '+1 months 00:00:00.000000',
                true,
            ],
        ];

    }

    #[DataProvider('provider_relative_times')]
    public function test_it_returns_relative_times(Closure $test_method, string $expect_result, bool $expect_zero_time)
    {
        $subject = $this->newSubject();
        // Capture the time before and after we do the calculation - time will pass during the test so we need to just
        // know that it's between the offset we would expect immediately before, and the offset we'd expect immediately
        // after.
        $expect_before= new DateTimeImmutable($expect_result);
        $result= $test_method($subject);
        $expect_after= new DateTimeImmutable($expect_result);

        $this->assertGreaterThanOrEqual($expect_before, $result);
        $this->assertLessThanOrEqual($expect_after, $result);

        if ($expect_zero_time) {
            $this->assertSame('00:00:00.000000', $result->format('H:i:s.u'));
        }
    }

    protected function newSubject()
    {
        return new RealtimeClock();
    }

    protected function assertBetween(mixed $expect_min, mixed $expect_max, mixed $actual, string $msg)
    {
        $this->assertGreaterThanOrEqual($expect_min, $actual, $msg);
        $this->assertLessThanOrEqual($expect_max, $actual, $msg);
    }

}
