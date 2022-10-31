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

    public static function encode($value, bool $pretty = TRUE): string
    {
        $json = json_encode($value, $pretty ? JSON_PRETTY_PRINT : 0);
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
