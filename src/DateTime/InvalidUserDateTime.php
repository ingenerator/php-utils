<?php
/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 * @licence   BSD-3-Clause
 */

namespace Ingenerator\PHPUtils\DateTime;


/**
 * Holds a user-entered invalid date string that could not be parsed, to allow overall entity
 * validation to catch it and legacy forms to re-display it.
 *
 * @package Ingenerator\Support\DateTime
 *
 * @deprecated You should be throwing an exception if the user input is not valid
 */
class InvalidUserDateTime extends \DateTimeImmutable
{
    /**
     * @var string
     */
    protected $input_string;

    /**
     * @param string $input_string
     */
    public function __construct($input_string)
    {
        $this->input_string = $input_string;
    }

    public static function createFromFormat($format, $time, $timezone = NULL): static
    {
        throw new \BadMethodCallException('Invalid call to '.__METHOD__);
    }

    public static function createFromMutable(\DateTime $dateTime): static
    {
        throw new \BadMethodCallException('Invalid call to '.__METHOD__);
    }

    public static function getLastErrors(): array|false
    {
        throw new \BadMethodCallException('Invalid call to '.__METHOD__);
    }

    public static function __set_state(array $array): \DateTimeImmutable
    {
        throw new \BadMethodCallException('Invalid call to '.__METHOD__);
    }

    public function format($format): string
    {
        return $this->input_string;
    }

    public function __toString()
    {
        return $this->input_string;
    }

    public function add($interval): \DateTimeImmutable
    {
        throw new \RuntimeException(
            'Cannot '.__METHOD__.' on invalid user date/time `'.$this->input_string.'`'
        );
    }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Alters the timestamp
     * @link http://www.php.net/manual/en/datetimeimmutable.modify.php
     *
     * @param string $modify <p>A date/time string. Valid formats are explained in
     *                       {@link http://www.php.net/manual/en/datetime.formats.php Date and Time Formats}.</p>
     *
     * @return static
     * Returns the {@link http://www.php.net/manual/en/class.datetimeimmutable.php DateTimeImmutable} object for method chaining or <b>FALSE</b> on failure.
     */
    public function modify($modify): \DateTimeImmutable
    {
        throw new \RuntimeException(
            'Cannot '.__METHOD__.' on invalid user date/time `'.$this->input_string.'`'
        );
    }

    public function setDate($year, $month, $day): \DateTimeImmutable
    {
        throw new \RuntimeException(
            'Cannot '.__METHOD__.' on invalid user date/time `'.$this->input_string.'`'
        );
    }

    public function setISODate($year, $week, $day = 1): \DateTimeImmutable
    {
        throw new \RuntimeException(
            'Cannot '.__METHOD__.' on invalid user date/time `'.$this->input_string.'`'
        );
    }

    public function setTime($hour, $minute, $second = 0, $microseconds = 0): \DateTimeImmutable
    {
        throw new \RuntimeException(
            'Cannot '.__METHOD__.' on invalid user date/time `'.$this->input_string.'`'
        );
    }

    public function setTimestamp($unixtimestamp): \DateTimeImmutable
    {
        throw new \RuntimeException(
            'Cannot '.__METHOD__.' on invalid user date/time `'.$this->input_string.'`'
        );
    }

    public function setTimezone($timezone): \DateTimeImmutable
    {
        throw new \RuntimeException(
            'Cannot '.__METHOD__.' on invalid user date/time `'.$this->input_string.'`'
        );
    }

    public function sub($interval): \DateTimeImmutable
    {
        throw new \RuntimeException(
            'Cannot '.__METHOD__.' on invalid user date/time `'.$this->input_string.'`'
        );
    }

    public function diff($datetime2, $absolute = FALSE): \DateInterval
    {
        throw new \RuntimeException(
            'Cannot '.__METHOD__.' on invalid user date/time `'.$this->input_string.'`'
        );
    }

    public function getOffset(): int
    {
        throw new \RuntimeException(
            'Cannot '.__METHOD__.' on invalid user date/time `'.$this->input_string.'`'
        );
    }

    public function getTimestamp(): int
    {
        throw new \RuntimeException(
            'Cannot '.__METHOD__.' on invalid user date/time `'.$this->input_string.'`'
        );
    }

    public function getTimezone(): \DateTimeZone|false
    {
        throw new \RuntimeException(
            'Cannot '.__METHOD__.' on invalid user date/time `'.$this->input_string.'`'
        );
    }

    public function __wakeup(): void
    {
        throw new \RuntimeException(
            'Cannot '.__METHOD__.' on invalid user date/time `'.$this->input_string.'`'
        );
    }


}
