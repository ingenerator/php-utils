<?php
/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 * @licence   proprietary
 */

namespace Ingenerator\PHPUtils\DateTime;


/**
 * Helpers to format a datetime as a string or return an empty value
 *
 * @package Ingenerator\Support\DateTime
 */
class DateString
{

    /**
     * @param \DateTimeImmutable|NULL $date
     * @param string                  $format
     * @param string                  $empty_value
     *
     * @return string
     */
    public static function format(\DateTimeImmutable $date = NULL, $format, $empty_value = '')
    {
        if ( ! $date) {
            return $empty_value;
        }

        return $date->format($format);
    }

    /**
     * Formats as Y-m-d H:i:s
     *
     * @param \DateTimeImmutable|NULL $date
     * @param string                  $empty_value
     *
     * @return string
     */
    public static function ymd(\DateTimeImmutable $date = NULL, $empty_value = '')
    {
        return static::format($date, 'Y-m-d', $empty_value);
    }

    /**
     * Formats as Y-m-d H:i:s
     *
     * @param \DateTimeImmutable|NULL $date
     * @param string                  $empty_value
     *
     * @return string
     */
    public static function ymdhis(\DateTimeImmutable $date = NULL, $empty_value = '')
    {
        return static::format($date, 'Y-m-d H:i:s', $empty_value);
    }
}
