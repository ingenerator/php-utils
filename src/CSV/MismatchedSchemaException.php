<?php
/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 * @licence   proprietary
 */

namespace Ingenerator\PHPUtils\CSV;

use Ingenerator\PHPUtils\StringEncoding\JSON;

class MismatchedSchemaException extends \InvalidArgumentException
{

    public static function forSchema(array $expected, array $actual)
    {
        return new static(
            'Mismatched row schema in CSV file:'."\n"
            .'Expected: '.JSON::encode($expected, FALSE)."\n"
            .'Actual:   '.JSON::encode($actual, FALSE)
        );
    }

}
