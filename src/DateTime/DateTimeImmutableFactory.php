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
            if ($date and $date->format($format) === $input) {
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

    public static function fromStrictFormat(string $value, string $format): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!'.$format, $value);
        if ($date && ($date->format($format) === $value)) {
            return $date;
        }

        throw new \InvalidArgumentException("`$value` is not a valid date/time in the format `$format`");
    }

    /**
     * Parses a time string in full ISO 8601 / RFC3339 format with optional milliseconds and timezone offset
     *
     * Can parse strings with any millisecond precision, truncating anything beyond 6 digits (which is the maximum
     * precision PHP supports). Copes with either `Z` or `+00:00` for the UTC timezone.
     *
     * Example valid inputs:
     *   - 2023-05-03T10:02:03Z
     *   - 2023-05-03T10:02:03.123456Z
     *   - 2023-05-03T10:02:03.123456789Z
     *   - 2023-05-03T10:02:03.123456789+01:00
     *   - 2023-05-03T10:02:03.123456789-01:30
     *
     * @param string $value
     *
     * @return DateTimeImmutable
     */
    public static function fromIso(string $value): DateTimeImmutable
    {
        // Cope with Z for Zulu time instead of +00:00 - PHP offers `p` for this, but that then doesn't accept '+00:00'
        $fixed_value = preg_replace('/Z/i', '+00:00', $value);

        // Pad / truncate milliseconds to 6 digits as that's the precision PHP can support
        // Regex is a bit dull here, but we need to be sure we can reliably find the (possibly absent)
        // millisecond segment without the risk of modifying unexpected parts of the string especially in
        // invalid values. Note that this will always replace the millis even in a 6-digit string, but it's simpler
        // than making the regex test for 0-5 or 7+ digits.
        $fixed_value = preg_replace_callback(
            '/(?P<hms>T\d{2}:\d{2}:\d{2})(\.(?P<millis>\d+))?(?P<tz_prefix>[+-])/',
            // Can't use sprintf because we want to truncate the milliseconds, not round them
            // So it's simpler to just handle this as a string and cut / pad as required.
            fn($matches) => $matches['hms']
                            .'.'
                            .substr(str_pad($matches['millis'], 6, '0'), 0, 6)
                            .$matches['tz_prefix'],
            $fixed_value
        );

        // Not using fromStrictFormat as I want to throw with the original value, not the parsed value
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.uP', $fixed_value);
        if (DateString::isoMS($date ?: NULL) === $fixed_value) {
            return $date;
        }
        throw new \InvalidArgumentException("`$value` cannot be parsed as a valid ISO date-time");
    }

    /**
     * Remove microseconds from a time (or current time, if nothing passed)
     *
     * @param DateTimeImmutable $time
     *
     * @return DateTimeImmutable
     */
    public static function zeroMicros(DateTimeImmutable $time = new DateTimeImmutable()): DateTimeImmutable
    {
        return $time->setTime(
            hour:        $time->format('H'),
            minute:      $time->format('i'),
            second:      $time->format('s'),
            microsecond: 0
        );
    }

}
