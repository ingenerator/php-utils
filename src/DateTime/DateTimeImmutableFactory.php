<?php
/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 * @licence   proprietary
 */

namespace Ingenerator\PHPUtils\DateTime;

use Ingenerator\PHPUtils\DateTime\InvalidUserDateTime;


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
     * Create from a unix timestamp (in seconds) in the current timezone
     *
     * PHP default behaviour when creating from a timestamp is to make the object UTC, this is a
     * short helper to work round that.
     *
     * @param int $timestamp
     *
     * @return \DateTimeImmutable
     */
    public static function atUnixSeconds(int $timestamp): \DateTimeImmutable
    {
        $dt = new \DateTimeImmutable;

        return $dt->setTimestamp($timestamp);
    }

    /**
     * Create a date from a d/m/y or d/m/Y date
     *
     * @param string $input
     *
     * @return \DateTimeImmutable|InvalidUserDateTime|null
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
            $date = \DateTimeImmutable::createFromFormat('!'.$format, $input);
            if ($date AND $date->format($format) === $input) {
                return $date;
            }
        }

        return new InvalidUserDateTime($input);
    }

    /**
     * @param string $input
     *
     * @return \DateTimeImmutable|InvalidUserDateTime|null
     */
    public static function fromUserDateTimeInput($input)
    {
        return static::fromPossibleFormats($input, ['Y-m-d H:i:s', 'Y-m-d H:i']);
    }

    /**
     * @param string $input
     * @return  \DateTimeImmutable|InvalidUserDateTime|null
     */
    public static function fromYmdInput($input)
    {
        return static::fromPossibleFormats($input, ['Y-m-d']);
    }

}
