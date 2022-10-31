<?php


namespace test\unit\Ingenerator\PHPUtils\StringEncoding;


use PHPUnit\Framework\TestCase;
use Ingenerator\PHPUtils\StringEncoding\JSON;
use Ingenerator\PHPUtils\StringEncoding\InvalidJSONException;

class JSONTest extends TestCase
{

    public function provider_valid_json()
    {
        return [
            ['1', 1],
            ['true', TRUE],
            ['"4"', '4'],
            ['null', NULL],
            ['{"foo": "bar", "baz": {"bin":"boo"}}', ['foo' => 'bar', 'baz' => ['bin' => 'boo']]]
        ];
    }

    /**
     * @dataProvider provider_valid_json
     */
    public function test_it_parses_valid_json_with_objects_as_arrays($json, $expect)
    {
        $this->assertSame($expect, JSON::decode($json));
    }

    /**
     * @testWith [null, "Cannot decode a null value"]
     *           ["", "Syntax error"]
     *           ["i am not json", "Syntax error"]
     */
    public function test_it_throws_on_parsing_invalid_json($value, $expect_msg)
    {
        $this->expectException(InvalidJSONException::class);
        $this->expectExceptionMessage($expect_msg);
        JSON::decode($value);
    }

    public function test_it_can_decode_json_as_explicit_array()
    {
        $this->assertSame(['foo' => 'bar'], JSON::decodeArray('{"foo": "bar"}'));
    }

    public function test_it_throws_from_decode_array_if_value_not_array()
    {
        $this->expectException(InvalidJSONException::class);
        $this->expectExceptionMessage('expected array, got string');
        JSON::decodeArray('"foo"');
    }

    /**
     * @testWith ["null"]
     */
    public function test_decode_array_returns_empty_array_for_json_null_input($input)
    {
        $this->assertSame([], JSON::decodeArray($input));
    }

    public function provider_valid_encode()
    {
        return [
            [1, FALSE, '1'],
            [['foo' => 'bar'], FALSE, '{"foo":"bar"}'],
            [['foo' => 'bar', 'baz' => 'ban'], FALSE, '{"foo":"bar","baz":"ban"}'],
            [['foo' => 'bar', 'baz' => 'ban'], TRUE, "{\n    \"foo\": \"bar\",\n    \"baz\": \"ban\"\n}"],
        ];
    }

    /**
     * @dataProvider provider_valid_encode
     */
    public function test_encode_encodes_json_prettily_or_not($val, $pretty, $expect)
    {
        $this->assertSame($expect, JSON::encode($val, $pretty));
    }

    public function test_it_throws_on_error_json_encoding()
    {
        $this->expectException(InvalidJSONException::class);
        // BAD UTF8 string content
        JSON::encode("\xC3\x2E");
    }

    public function test_it_can_prettify_existing_json()
    {
        $this->assertSame(
            "{\n    \"foo\": \"bar\",\n    \"baz\": \"ban\"\n}",
            JSON::prettify('{"foo":"bar","baz":"ban"}')
        );
    }

}
