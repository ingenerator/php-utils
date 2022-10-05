<?php

namespace Ingenerator\PHPUtils\StringEncoding;

use Ingenerator\PHPUtils\StringEncoding\InvalidJSONException;
use function json_last_error_msg;

class JSON
{

    public static function decode(?string $json)
    {
        try {
            $result = json_decode($json, TRUE);
        } catch (\ErrorException $e){
            throw new InvalidJSONException('Invalid JSON: ' . json_last_error_msg());
        }
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidJSONException('Invalid JSON: ' . json_last_error_msg());
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
