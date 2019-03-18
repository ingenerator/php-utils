<?php


namespace test\unit\Ingenerator\PHPUtils\unit\DateTime\Clock;

use Ingenerator\PHPUtils\DateTime\Clock\StoppedMockClock;
use PHPUnit\Framework\TestCase;

class StoppedMockClockTest extends TestCase
{

    public function test_it_is_initialisable_now()
    {
        $clock = StoppedMockClock::atNow();
        $this->assertEquals(
            new \DateTimeImmutable,
            $clock->getDateTime(),
            'Starts at the right time',
            1
        );
    }

    public function provider_at_fixed()
    {
        return [
            [
                '2019-03-04 10:02:03',
                new \DateTimeImmutable('2019-03-04 10:02:03'),
                1551693723.0
            ],
            [
                new \DateTimeImmutable('2019-03-04 10:02:03'),
                new \DateTimeImmutable('2019-03-04 10:02:03'),
                1551693723.0
            ],
        ];
    }


    /**
     * @dataProvider provider_at_fixed
     */
    public function test_it_is_initialisable_at_fixed_time_from_string_or_object($at_what, $expect_time, $expect_micro)
    {
        $clock = StoppedMockClock::at($at_what);
        $this->assertEquals($expect_time, $clock->getDateTime());
        $this->assertSame($expect_micro, $clock->getMicrotime());
    }

    public function test_it_is_initialisable_at_fixed_microtime()
    {
        $clock = StoppedMockClock::atMicrotime(1551693723.1239);
        $this->assertSame(1551693723.1239, $clock->getMicrotime());
        $this->assertSame('2019-03-04 10:02:03', $clock->getDateTime()->format('Y-m-d H:i:s'));
    }

    public function test_it_is_initialisable_at_a_date_interval_in_the_past()
    {
        $clock = StoppedMockClock::atTimeAgo('P3D');
        $ago = (new \DateTimeImmutable)->sub(new \DateInterval('P3D'));
        $this->assertEquals($ago, $clock->getDateTime(), 'Time is at correct interval', 1);
    }

    public function test_it_holds_its_time_forever_in_real_life()
    {
        $clock = StoppedMockClock::atNow();
        $start_microtime = $clock->getMicrotime();
        $start_time = $clock->getDateTime();
        sleep(2);
        $this->assertEquals($start_time, $clock->getDateTime(), 'Stays at the same time');
        $this->assertSame($start_microtime, $clock->getMicrotime(), 'Stays at the same microtime');
    }

    public function test_it_advances_time_after_each_tick()
    {
        $clock = StoppedMockClock::at('2019-01-05 10:03:02');
        $this->assertSame(1546682582.0, $clock->getMicrotime(), 'Correct starting microtime');

        $clock->tick(new \DateInterval('P1D'));
        $this->assertEquals(new \DateTimeImmutable('2019-01-06 10:03:02'), $clock->getDateTime());
        $this->assertSame(1546768982.0, $clock->getMicrotime());
    }

    public function test_it_advances_time_after_each_tick_microseconds()
    {
        $clock = StoppedMockClock::atMicrotime(1546682582.150);
        $this->assertEquals(new \DateTimeImmutable('2019-01-05 10:03:02'), $clock->getDateTime());

        $clock->tickMicroseconds(150000);
        $this->assertSame(1546682582.300, round($clock->getMicrotime(), 3));
        $this->assertEquals(new \DateTimeImmutable('2019-01-05 10:03:02'), $clock->getDateTime(), 'DateTime not changed by sub-second tick');


        $clock->tickMicroseconds(750000);
        $this->assertSame(1546682583.050, round($clock->getMicrotime(), 3));
        $this->assertEquals(new \DateTimeImmutable('2019-01-05 10:03:03'), $clock->getDateTime(), 'DateTime changed after second boundary');
    }

    public function test_its_usleep_is_immediate_but_advances_time()
    {
        $clock = StoppedMockClock::atMicrotime(1546682582.05);
        $start = microtime(TRUE);
        $clock->usleep(900000);
        $real_ms = 1000 * (microtime(TRUE) - $start);
        $this->assertLessThan(50, $real_ms, 'Should not actually sleep');
        $this->assertSame(1546682582.95, round($clock->getMicrotime(), 3), 'Should update time');
    }

    public function provider_assert_slept_fails()
    {
        return [
            [
                function () {
                },
                [15],
                'Never slept at all'
            ],
            [
                function (StoppedMockClock $clock) {
                    $clock->usleep(150);
                },
                [15],
                'Wrong amount of sleep'
            ],
            [
                function (StoppedMockClock $clock) {
                    $clock->usleep(150);
                },
                [150, 30],
                'Incorrect number of sleeps'
            ],
            [
                function (StoppedMockClock $clock) {
                    $clock->usleep(150);
                    $clock->usleep(10);
                },
                [10, 150],
                'Sleeps in wrong order'
            ],
        ];
    }

    /**
     * @dataProvider provider_assert_slept_fails
     */
    public function test_assert_slept_fails_if_not_slept_for_expected_intervals($callback, $expected, $msg)
    {
        $clock = StoppedMockClock::atNow();
        $callback($clock);
        $e = NULL;
        try {
            $clock->assertSlept($expected, $msg);
        } catch (\Exception $e) {
        }
        $this->assertInstanceOf(\Exception::class, $e, 'Should have thrown');
        // Do it like this to make it type-safe for old and new phpunit
        $this->assertContains('ExpectationFailedException', get_class($e), 'Should be assertion exception');
    }

    public function test_assert_slept_passes_if_slept_for_expected_intervals()
    {
        $clock = StoppedMockClock::atNow();
        $clock->usleep(50000);
        $clock->usleep(20000);
        $clock->usleep(15000);
        $this->assertNull(
            $clock->assertSlept([50000, 20000, 15000])
        );
    }
}