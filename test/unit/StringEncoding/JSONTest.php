<?php

declare(strict_types=1);

namespace test\unit\Ingenerator\PHPUtils\StringEncoding;

use Ingenerator\PHPUtils\StringEncoding\InvalidJSONException;
use Ingenerator\PHPUtils\StringEncoding\JSON;
use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class JSONTest extends TestCase
{
    /**
     * @return Iterator<int, array<mixed>>
     */
    public static function provider_valid_json(): Iterator
    {
        yield ['1', 1];
        yield ['true', true];
        yield ['"4"', '4'];
        yield ['null', null];
        yield ['{"foo": "bar", "baz": {"bin":"boo"}}', ['foo' => 'bar', 'baz' => ['bin' => 'boo']]];
    }

    #[DataProvider('provider_valid_json')]
    public function testItParsesValidJsonWithObjectsAsArrays(string $json, int|bool|string|array|null $expect): void
    {
        $this->assertSame($expect, JSON::decode($json));
    }

    #[TestWith([null, 'Cannot decode a null value'])]
    #[TestWith(['', 'Syntax error'])]
    #[TestWith(['i am not json', 'Syntax error'])]
    public function testItThrowsOnParsingInvalidJson($value, $expect_msg): void
    {
        $this->expectException(InvalidJSONException::class);
        $this->expectExceptionMessage($expect_msg);
        JSON::decode($value);
    }

    public function testItCanDecodeJsonAsExplicitArray(): void
    {
        $this->assertSame(['foo' => 'bar'], JSON::decodeArray('{"foo": "bar"}'));
    }

    public function testItThrowsFromDecodeArrayIfValueNotArray(): void
    {
        $this->expectException(InvalidJSONException::class);
        $this->expectExceptionMessage('expected array, got string');
        JSON::decodeArray('"foo"');
    }

    public function testDecodeArrayReturnsEmptyArrayForJsonNullInput(): void
    {
        $this->assertSame([], JSON::decodeArray(json_encode(null)));
    }

    /**
     * @return Iterator<int, array<(array<string, string> | bool | int | string)>>
     */
    public static function provider_valid_encode(): Iterator
    {
        yield [1, false, '1'];
        yield [['foo' => 'bar'], false, '{"foo":"bar"}'];
        yield [['foo' => 'bar', 'baz' => 'ban'], false, '{"foo":"bar","baz":"ban"}'];
        yield [['foo' => 'bar', 'baz' => 'ban'], true, "{\n    \"foo\": \"bar\",\n    \"baz\": \"ban\"\n}"];
    }

    #[DataProvider('provider_valid_encode')]
    public function testEncodeEncodesJsonPrettilyOrNot(int|array $val, bool $pretty, string $expect): void
    {
        $this->assertSame($expect, JSON::encode($val, $pretty));
    }

    public function testItThrowsOnErrorJsonEncoding(): void
    {
        $this->expectException(InvalidJSONException::class);
        // BAD UTF8 string content
        JSON::encode("\xC3\x2E");
    }

    public function testItCanPrettifyExistingJson(): void
    {
        $this->assertSame(
            "{\n    \"foo\": \"bar\",\n    \"baz\": \"ban\"\n}",
            JSON::prettify('{"foo":"bar","baz":"ban"}')
        );
    }
}
