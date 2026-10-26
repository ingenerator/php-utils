<?php

namespace test\unit\Ingenerator\PHPUtils\StringEncoding;

use Ingenerator\PHPUtils\StringEncoding\InvalidJSONException;
use Ingenerator\PHPUtils\StringEncoding\JSON;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class JSONTest extends TestCase
{
    /**
     * @return array<int, mixed[]>
     */
    public static function provider_valid_json(): array
    {
        return [
            ['1', 1],
            ['true', true],
            ['"4"', '4'],
            ['null', null],
            ['{"foo": "bar", "baz": {"bin":"boo"}}', ['foo' => 'bar', 'baz' => ['bin' => 'boo']]],
        ];
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
     * @return array<int, int[]|bool[]|string[]|array<string, string>[]>
     */
    public static function provider_valid_encode(): array
    {
        return [
            [1, false, '1'],
            [['foo' => 'bar'], false, '{"foo":"bar"}'],
            [['foo' => 'bar', 'baz' => 'ban'], false, '{"foo":"bar","baz":"ban"}'],
            [['foo' => 'bar', 'baz' => 'ban'], true, "{\n    \"foo\": \"bar\",\n    \"baz\": \"ban\"\n}"],
        ];
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
