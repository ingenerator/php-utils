<?php
/**
 * @author    Craig Gosman <craig@ingenerator.com>
 * @licence   BSD-3-Clause
 */

namespace Ingenerator\PHPUtils\DateTime;

class DateTimeDiff
{

    /**
     * Calculates the difference between two DateTimes in microseconds
     *
     * @param \DateTimeInterface $date1
     * @param \DateTimeInterface $date2
     *
     * @return int
     */
    public static function microsBetween(\DateTimeInterface $date1, \DateTimeInterface $date2): int
    {
        $seconds      = (int) $date2->format('U') - (int) $date1->format('U');
        $microseconds = (int) $date2->format('u') - (int) $date1->format('u');

        // multiply the difference in unix timestamps (seconds) by 1 million to get microseconds
        // add the microsecond difference
        return ($seconds * 1_000_000) + $microseconds;
    }
}
