<?php

declare(strict_types=1);

namespace Ingenerator\PHPUtils\StringEncoding;

use JsonException;

class JSON
{
    public static function decode(?string $json): mixed
    {
        // NOTE: it is deprecated to call this with a null argument, which has always thrown an InvalidJSONException.
        // The typehint is left as ?string for backwards compatibility
        if ($json === null) {
            throw new InvalidJSONException('Invalid JSON: Cannot decode a null value');
        }

        try {
            return json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidJSONException('Invalid JSON: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @return array<mixed>
     */
    public static function decodeArray(string $json): array
    {
        $value = static::decode($json);
        if ($value && !is_array($value)) {
            throw new InvalidJSONException('Unexepected JSON value - expected array, got ' . gettype($value));
        }

        return $value ?: [];
    }

    /**
     * @param bool $escaped_slashes defaults true to match the PHP default
     */
    public static function encode(
        mixed $value,
        bool $pretty = true,
        bool $escaped_slashes = true,
    ): string {
        $flags = (
            ($pretty ? JSON_PRETTY_PRINT : 0)
            |
            ($escaped_slashes ? 0 : JSON_UNESCAPED_SLASHES)
            |
            JSON_THROW_ON_ERROR
        );

        try {
            return json_encode($value, $flags);
        } catch (JsonException $e) {
            throw new InvalidJSONException('Could not encode as JSON: '.$e->getMessage(), $e->getCode(), $e);
        }
    }

    public static function prettify(string $json): string
    {
        return static::encode(static::decode($json));
    }
}
