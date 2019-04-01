<?php
/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 * @licence   proprietary
 */

namespace test\unit\Ingenerator\PHPUtils\DateTime\Clock;


use Ingenerator\PHPUtils\DateTime\Clock\RealtimeClock;
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
        $this->assertInstanceOf(\DateTimeImmutable::class, $time);
        // Allow for time changing during the test
        $this->assertEquals(new \DateTimeImmutable, $time, 'Should be roughly the real time', 1);
    }

    public function test_it_returns_microtime()
    {
        $mt = $this->newSubject()->getMicrotime();
        $this->assertInternalType('float', $mt);
        $this->assertEquals(\microtime(TRUE), $mt, 'Should be roughly the real microtime', 0.2);
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

        $this->assertEquals(
            2,
            $end_ts->getTimestamp() - $start_ts->getTimestamp(),
            'Seconds move',
            1
        );
        $this->assertEquals(
            2.5,
            $end_mt - $start_mt,
            'microseconds should be about right',
            0.2
        );
    }


    protected function newSubject()
    {
        return new RealtimeClock();
    }

}
