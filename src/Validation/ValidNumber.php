<?php
/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 * @licence   BSD-3-Clause
 */

namespace Ingenerator\PHPUtils\Validation;


class ValidNumber
{
    protected function __construct() { }

    /**
     * @param string    $number
     * @param int|float $min
     *
     * @return bool
     */
    public static function minimum($number, $min)
    {
        if ( ! is_numeric($number)) {
            return FALSE;
        }

        return ($number >= $min);
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
            case 'minimum':
                return static::class.'::'.$rulename;
            default:
                throw new \InvalidArgumentException('Unknown rule '.__CLASS__.'::'.$rulename);
        }
    }

}
