<?php

declare(strict_types=1);

/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 *
 * @licence   BSD-3-Clause
 */

namespace Ingenerator\PHPUtils\CSV;

use Ingenerator\PHPUtils\StringEncoding\JSON;
use InvalidArgumentException;

final class MismatchedSchemaException extends InvalidArgumentException
{
    public static function forSchema(array $expected, array $actual): self
    {
        return new self(
            'Mismatched row schema in CSV file:'."\n"
            .'Expected: '.JSON::encode($expected, false)."\n"
            .'Actual:   '.JSON::encode($actual, false)
        );
    }
}
