<?php
/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 * @licence   BSD-3-Clause
 */

namespace Ingenerator\PHPUtils\DateTime\Clock;

use DateInterval;
use DateTimeImmutable;
use Ingenerator\PHPUtils\DateTime\DateTimeImmutableFactory;

/**
 * Simple wrapper around current date/time methods to allow easy injection of fake time in
 * dependent classes
 *
 * @package Ingenerator\Util
 */
class RealtimeClock
{
    /**
     * @return DateTimeImmutable
     */
    public function getDateTime()
    {
        return new DateTimeImmutable;
    }

    /**
     * @return float
     */
    public function getMicrotime()
    {
        return \microtime(TRUE);
    }

    /**
     * @param $microseconds
     */
    public function usleep($microseconds)
    {
        \usleep($microseconds);
    }

    /**
     * Calculate a relative date in the past, optionally truncating time to 0 - sugar for getDateTime()->sub()
     */
    public function ago(DateInterval $interval, bool $date_only = FALSE): DateTimeImmutable
    {
        $result = $this->getDateTime()->sub($interval);

        return match ($date_only) {
            FALSE => $result,
            TRUE => DateTimeImmutableFactory::zeroTime($result)
        };
    }

    /**
     * Calculate a relative date in the future, optionally truncating time to 0 - sugar for getDateTime()->add()
     */
    public function future(DateInterval $interval, bool $date_only = FALSE): DateTimeImmutable
    {
        $result = $this->getDateTime()->add($interval);

        return match ($date_only) {
            FALSE => $result,
            TRUE => DateTimeImmutableFactory::zeroTime($result)
        };
    }
}
