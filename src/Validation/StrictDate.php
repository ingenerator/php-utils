<?php
/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 * @licence   proprietary
 */

namespace Ingenerator\PHPUtils\Validation;


use Ingenerator\PHPUtils\DateTime\InvalidUserDateTime;

class StrictDate
{

    /**
     * Validate that value of one field is a date after the value of another
     *
     * @param \ArrayAccess $validation
     * @param string       $from_field
     * @param string       $to_field
     *
     * @return bool
     */
    public static function date_after(\ArrayAccess $validation, $from_field, $to_field)
    {
        if ( ! $dates = static::get_valid_date_pair($validation, $from_field, $to_field)) {
            // Return true if either value is empty or invalid, this will be picked up by other rules
            return TRUE;
        }

        list($from, $to) = $dates;

        return ($to > $from);
    }

    /**
     * Validate that value of one field is a date on or after value of another (>= ignoring time)
     *
     * @param \ArrayAccess $validation
     * @param string       $from_field
     * @param string       $to_field
     *
     * @return bool
     */
    public static function date_on_or_after(\ArrayAccess $validation, $from_field, $to_field)
    {
        if ( ! $dates = static::get_valid_date_pair($validation, $from_field, $to_field)) {
            // Return true if either value is empty or invalid, this will be picked up by other rules
            return TRUE;
        }

        list($from, $to) = $dates;

        return ($to->format('Y-m-d') >= $from->format('Y-m-d'));
    }

    /**
     * @param \ArrayAccess $validation
     * @param string       $from_field
     * @param string       $to_field
     *
     * @return \DateTimeImmutable[]
     */
    protected static function get_valid_date_pair(\ArrayAccess $validation, $from_field, $to_field)
    {
        $from = $validation[$from_field];
        $to   = $validation[$to_field];

        // Return true if either value is empty or invalid, this will be picked up by other rules
        if ($from instanceof InvalidUserDateTime OR ! $from instanceof \DateTimeImmutable) {
            return NULL;
        }
        if ($to instanceof InvalidUserDateTime OR ! $to instanceof \DateTimeImmutable) {
            return NULL;
        }

        return [$from, $to];
    }

    /**
     * Value is NULL or a valid DateTimeImmutable - not an InvalidUserDateTime
     *
     * @param mixed $value
     *
     * @return bool
     */
    public static function date_immutable($value)
    {
        return static::datetime_immutable($value);
    }

    /**
     * Value is NULL or a valid DateTimeImmutable - not an InvalidUserDateTime
     *
     * @param mixed $value
     *
     * @return bool
     */
    public static function datetime_immutable($value)
    {
        if ($value === NULL) {
            return TRUE;
        } elseif ($value instanceof InvalidUserDateTime) {
            return FALSE;
        } elseif ($value instanceof \DateTimeImmutable) {
            return TRUE;
        } else {
            return FALSE;
        }
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
        $dt = \DateTimeImmutable::createFromFormat($format, $value);

        return ($dt AND ($dt->format($format) === $value));
    }

    /**
     * Shorthand way to specify the fully qualified validator callback
     *
     * @param string $rulename
     *
     * @return string
     */
    public static function rule($rulename)
    {
        switch ($rulename) {
            case 'date_after':
            case 'date_on_or_after':
            case 'date_immutable':
            case 'datetime_immutable':
            case 'iso_date':
            case 'iso_datetime':
                return static::class.'::'.$rulename;
            default:
                throw new \InvalidArgumentException('Unknown rule '.__CLASS__.'::'.$rulename);
        }
    }
}
