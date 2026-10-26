<?php

declare(strict_types=1);

/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 *
 * @licence   BSD-3-Clause
 */

namespace Ingenerator\PHPUtils\Validation;

use InvalidArgumentException;

class ValidNumber
{
    protected function __construct()
    {
    }

    /**
     * @param string    $number
     * @param int|float $min
     */
    public static function minimum($number, $min): bool
    {
        if (!is_numeric($number)) {
            return false;
        }

        return $number >= $min;
    }

    /**
     * Shorthand way to specify the fully qualified validator callback.
     *
     * @param string $rulename
     */
    public static function rule($rulename): string
    {
        return match ($rulename) {
            'minimum' => static::class.'::'.$rulename,
            default => throw new InvalidArgumentException('Unknown rule '.self::class.'::'.$rulename),
        };
    }
}
