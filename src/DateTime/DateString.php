<?php

/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 *
 * @licence   BSD-3-Clause
 */

namespace Ingenerator\PHPUtils\DateTime;

use DateTimeImmutable;

/**
 * Helpers to format a datetime as a string or return an empty value.
 */
class DateString
{
    public static function format(?DateTimeImmutable $date, string $format, string $empty_value = ''): string
    {
        if (!$date instanceof DateTimeImmutable) {
            return $empty_value;
        }

        return $date->format($format);
    }

    /**
     * Formats to ISO8601 (the real one not the PHP one :-O) with microsecond precision.
     */
    public static function isoMS(?DateTimeImmutable $date, ?string $empty_value = ''): ?string
    {
        return static::format($date, 'Y-m-d\TH:i:s.uP', $empty_value);
    }

    /**
     * Formats as Y-m-d H:i:s.
     */
    public static function ymd(?DateTimeImmutable $date, string $empty_value = ''): string
    {
        return static::format($date, 'Y-m-d', $empty_value);
    }

    /**
     * Formats as Y-m-d H:i:s.
     */
    public static function ymdhis(?DateTimeImmutable $date, string $empty_value = ''): string
    {
        return static::format($date, 'Y-m-d H:i:s', $empty_value);
    }
}
