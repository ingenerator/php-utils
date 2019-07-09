<?php
/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 * @licence   proprietary
 */

namespace Ingenerator\PHPUtils\DateTime\Clock;

use Ingenerator\PHPUtils\DateTime\Clock\RealtimeClock;
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
     * Use descriptive static constructors for a new instance
     */
    protected function __construct(\DateTimeImmutable $start_time)
    {
        $this->current_microtime = (float) $start_time->getTimestamp();
    }

    /**
     * @param float $microtime
     *
     * @return static
     */
    public static function atMicrotime($microtime)
    {
        $inst                    = new static(new \DateTimeImmutable);
        $inst->current_microtime = $microtime;

        return $inst;
    }

    /**
     * @return static
     */
    public static function atNow()
    {
        return new static(new \DateTimeImmutable);
    }

    /**
     * @param string $time
     *
     * @return static
     */
    public static function at($time)
    {
        if ( ! $time instanceof \DateTimeImmutable) {
            $time = new \DateTimeImmutable($time);
        }

        return new static($time);
    }

    /**
     * @param string $interval_spec
     *
     * @return static
     */
    public static function atTimeAgo($interval_spec)
    {
        $now = new \DateTimeImmutable;
        $ago = $now->sub(new \DateInterval($interval_spec));

        return new static($ago);
    }

    public function getDateTime()
    {
        return (new \DateTimeImmutable)->setTimestamp(\floor($this->current_microtime));
    }

    public function getMicrotime()
    {
        return $this->current_microtime;
    }

    public function tick(\DateInterval $period)
    {
        $now                     = $this->getDateTime()->add($period);
        $this->current_microtime = (float) $now->getTimestamp();
    }

    /**
     * @param float $microseconds
     */
    public function tickMicroseconds($microseconds)
    {
        $this->current_microtime += ($microseconds / 1000000);
    }

    public function usleep($microseconds)
    {
        $this->tickMicroseconds($microseconds);
        $this->sleeps[] = $microseconds;
    }

    public function assertSlept(array $expected, $msg = '')
    {
        Assert::assertSame($expected, $this->sleeps, $msg);
    }

    public function assertNeverSlept($msg = '')
    {
        Assert::assertNull($this->sleeps, $msg);
    }

}
