<?php

namespace Ingenerator\PHPUtils\StringEncoding;

use Ingenerator\PHPUtils\StringEncoding\InvalidJSONException;
use function json_last_error_msg;

class JSON
{

    public static function decode(?string $json)
    {
        // NOTE: it is deprecated to call this with a null argument, which has always thrown an InvalidJSONException.
        // The typehint is left as ?string for backwards compatibility
        if ($json === NULL) {
            throw new InvalidJSONException('Invalid JSON: Cannot decode a null value');
        }

        $result = json_decode($json, TRUE);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidJSONException('Invalid JSON: '.json_last_error_msg());
        }

        return $result;
    }

    public static function decodeArray(string $json): array
    {
        $value = static::decode($json);
        if ($value AND ! is_array($value)) {
            throw new \Ingenerator\PHPUtils\StringEncoding\InvalidJSONException('Unexepected JSON value - expected array, got ' . gettype($value));
        }
        return $value ?: [];
    }

    /**
     * @param mixed $value
     * @param bool $pretty
     * @param bool $escaped_slashes defaults true to match the PHP default
     *
     * @return string
     * @throws \Ingenerator\PHPUtils\StringEncoding\InvalidJSONException
     */
    public static function encode(
        $value,
        bool $pretty = true,
        bool $escaped_slashes = true,
    ): string {
        $flags = (
            ($pretty ? JSON_PRETTY_PRINT : 0)
            |
            ($escaped_slashes ? 0 : JSON_UNESCAPED_SLASHES)
        );

        $json = json_encode($value, $flags);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Ingenerator\PHPUtils\StringEncoding\InvalidJSONException('Could not encode as JSON : ' . json_last_error_msg());
        }
        return $json;
    }

    /**
     * @param string $json
     * @return string
     */
    public static function prettify(string $json): string
    {
        return static::encode(static::decode($json));
    }
}
