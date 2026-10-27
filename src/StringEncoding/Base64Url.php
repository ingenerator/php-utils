<?php

declare(strict_types=1);

namespace Ingenerator\PHPUtils\StringEncoding;

/**
 * Helper for base64url encoded strings - these are base64, but with the + and / replaced with url-safe characters and
 * the trailing == padding trimmed.
 */
class Base64Url
{
    public static function encode(string $value): string
    {
        $base64 = base64_encode($value);

        return rtrim(strtr($base64, ['+' => '-', '/' => '_']), '=');
    }

    public static function decode(string $value): string|false
    {
        $string = strtr($value, ['-' => '+', '_' => '/']);

        return base64_decode($string, true);
    }
}
