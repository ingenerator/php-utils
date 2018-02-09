<?php
/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 * @licence   proprietary
 */

namespace Ingenerator\PHPUtils\CSV;


class MismatchedSchemaException extends \InvalidArgumentException
{

    public static function forSchema(array $expected, array $actual)
    {
        return new static(
            'Mismatched row schema in CSV file:'."\n"
            .'Expected: '.json_encode($expected)."\n"
            .'Actual:   '.json_encode($actual)
        );
    }

}
