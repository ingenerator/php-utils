<?php

/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 *
 * @licence   BSD-3-Clause
 */

namespace Ingenerator\PHPUtils\Validation;

use ArrayAccess;
use DateTimeImmutable;
use InvalidArgumentException;

class StrictDate
{
    /**
     * Validate that value of one field is a date after the value of another.
     *
     * @param string       $from_field
     * @param string       $to_field
     *
     * @deprecated validate input before creating DateTimeImmutable objects
     *
     * @return bool
     */
    public static function date_after(ArrayAccess $validation, $from_field, $to_field)
    {
        $from = $validation[$from_field];
        $to = $validation[$to_field];

        if (!$from instanceof DateTimeImmutable || !$to instanceof DateTimeImmutable) {
            // Return true if either value is empty or invalid, this will be picked up by other rules
            return true;
        }

        return $to > $from;
    }

    /**
     * Validate that value of one field is a date on or after value of another (>= ignoring time).
     *
     * @param string       $from_field
     * @param string       $to_field
     *
     * @deprecated validate input before creating DateTimeImmutable objects
     *
     * @return bool
     */
    public static function date_on_or_after(ArrayAccess $validation, $from_field, $to_field)
    {
        $from = $validation[$from_field];
        $to = $validation[$to_field];

        if (!$from instanceof DateTimeImmutable || !$to instanceof DateTimeImmutable) {
            // Return true if either value is empty or invalid, this will be picked up by other rules
            return true;
        }

        return $to->format('Y-m-d') >= $from->format('Y-m-d');
    }

    /**
     * Value is NULL or a valid DateTimeImmutable.
     *
     * @return bool
     */
    public static function date_immutable($value)
    {
        return static::datetime_immutable($value);
    }

    /**
     * Value is NULL or a valid DateTimeImmutable.
     *
     * @return bool
     */
    public static function datetime_immutable($value)
    {
        if ($value === null) {
            return true;
        }

        return $value instanceof DateTimeImmutable;
    }

    /**
     * @param string $value
     *
     * @return bool
     */
    public static function iso_datetime($value)
    {
        return self::is_datetime_format('Y-m-d H:i:s', $value);
    }

    /**
     * @param string $value
     *
     * @return bool
     */
    public static function iso_date($value)
    {
        return self::is_datetime_format('Y-m-d', $value);
    }

    protected static function is_datetime_format($format, $value)
    {
        $dt = DateTimeImmutable::createFromFormat($format, (string) $value);

        return $dt && $dt->format($format) === $value;
    }

    /**
     * Shorthand way to specify the fully qualified validator callback.
     *
     * @param string $rulename
     *
     * @return string
     */
    public static function rule($rulename)
    {
        return match ($rulename) {
            'date_after',
            'date_on_or_after',
            'date_immutable',
            'datetime_immutable',
            'iso_date',
            'iso_datetime' => static::class.'::'.$rulename,
            default => throw new InvalidArgumentException('Unknown rule '.self::class.'::'.$rulename),
        };
    }
}
