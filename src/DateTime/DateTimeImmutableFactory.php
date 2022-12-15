<?php
/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 * @licence   BSD-3-Clause
 */

namespace Ingenerator\PHPUtils\DateTime;

use DateTimeImmutable;


/**
 * Helper methods to make \DateTimeImmutable objects
 *
 * @package Ingenerator\Support
 */
class DateTimeImmutableFactory
{
    protected function __construct()
    {
        // no creating instances of this
    }

    /**
     * Create from a unix timestamp with microsecond precision (e.g. a microtime() float) in current timezone
     *
     * As with ::atUnixSeconds this works round the default PHP behaviour that creating from a timestampy value
     * always uses UTC rather than the default / current timezone.
     *
     * It further works round a PHP edge case that a float with a .00 casts to a string with no decimal point,
     * which causes createFromFormat('U.u') to fail on exact seconds unless you format the string with sprintf
     * to have the expected number of decimal places.
     *
     * @param float $microtime
     *
     * @return DateTimeImmutable
     */
    public static function atMicrotime(float $microtime): DateTimeImmutable
    {
        // Work round some PHP edge cases.
        // First, a float with a .00 casts to string with no decimal point, which causes the createFromFormat to fail
        // on exact seconds. Use sprintf to ensure there's always a decimal present
        $dt = DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', $microtime));

        // Second, creating from unix timestamp of any kind always sets the TZ to UTC (*even* if you specify a timezone
        // in the constructor).
        // So cast it back to the default timezone for consistency with other date time constructor formats
        return $dt->setTimezone(new \DateTimeZone(\date_default_timezone_get()));
    }

    /**
     * Create from a unix timestamp (in seconds) in the current timezone
     *
     * PHP default behaviour when creating from a timestamp is to make the object UTC, this is a
     * short helper to work round that.
     *
     * @param int $timestamp
     *
     * @return DateTimeImmutable
     */
    public static function atUnixSeconds(int $timestamp): DateTimeImmutable
    {
        return static::atMicrotime($timestamp);
    }

    /**
     * Create a date from a d/m/y or d/m/Y date
     *
     * @param string $input
     *
     * @return DateTimeImmutable|InvalidUserDateTime|null
     *
     * @deprecated Validate your input and create a DateTimeImmutable adhering to a strict format
     */
    public static function fromUserDateInput($input)
    {
        return static::fromPossibleFormats($input, ['Y-m-d', 'd/m/Y', 'd/m/y', 'j/n/Y', 'j/n/y']);
    }

    protected static function fromPossibleFormats($input, array $formats)
    {
        if ( ! $input) {
            return NULL;
        }
        foreach ($formats as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $input);
            if ($date AND $date->format($format) === $input) {
                return $date;
            }
        }

        return new InvalidUserDateTime($input);
    }

    /**
     * @param string $input
     *
     * @return DateTimeImmutable|InvalidUserDateTime|null
     *
     * @deprecated Validate your input and create a DateTimeImmutable adhering to a strict format
     */
    public static function fromUserDateTimeInput($input)
    {
        return static::fromPossibleFormats($input, ['Y-m-d H:i:s', 'Y-m-d H:i']);
    }

    /**
     * @param string $input
     *
     * @return  DateTimeImmutable|InvalidUserDateTime|null
     *
     * @deprecated Validate your input and create a DateTimeImmutable adhering to a strict format
     */
    public static function fromYmdInput($input)
    {
        return static::fromPossibleFormats($input, ['Y-m-d']);
    }

    public static function fromYmdHis(string $input): DateTimeImmutable
    {
        $format = 'Y-m-d H:i:s';
        $date   = DateTimeImmutable::createFromFormat('!'.$format, $input);
        if ($date and $date->format($format) === $input) {
            return $date;
        }
        throw new \InvalidArgumentException($input.' is not in the format Y-m-d H:i:s');
    }

    public static function fromStrictFormat(string $value, string $format): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!'.$format, $value);
        if ($date && ($date->format($format) === $value)) {
            return $date;
        }

        throw new \InvalidArgumentException("`$value` is not a valid date/time in the format `$format`");
    }

}
