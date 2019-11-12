<?php

namespace Ingenerator\PHPUtils\StringEncoding;

/**
 * Helper for base64url encoded strings - these are base64, but with the + and / replaced with url-safe characters and
 * the trailing == padding trimmed
 */
class Base64Url
{
    /**
     * @param string $value
     *
     * @return string
     */
    public static function encode(string $value): string
    {
        $base64 = base64_encode($value);

        return rtrim(strtr($base64, ['+' => '-', '/' => '_']), '=');
    }

    /**
     * @param string $value
     *
     * @return bool|string
     */
    public static function decode(string $value)
    {
        $string = strtr($value, ['-' => '+', '_' => '/']);

        return base64_decode($string, TRUE);

    }
}
