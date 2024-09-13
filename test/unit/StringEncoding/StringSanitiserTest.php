<?php

namespace test\unit\Ingenerator\PHPUtils\StringEncoding;

use Ingenerator\PHPUtils\StringEncoding\StringSanitiser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StringSanitiserTest extends TestCase
{

    public static function provider_printable_utf8(): array
    {
        return [
            'valid ascii' => [
                'I am valid text',
            ],
            'valid ascii with newlines and tabs' => [
                "I am valid\nmultiline\ttext with whitespace",
            ],
            'valid ascii with windows newlines' => [
                "I am valid\r\nmultiline fróm Windows",
            ],
            'valid UTF8' => [
                "Hello from Denmark with æøå",
            ],
            'ascii with BEL control characters' => [
                "Ring the \x07",
                "Ring the �",
            ],
            'ascii with leading NUL control characters' => [
                "\x00nully null",
                "�nully null",
            ],
            'ascii with trailing NUL control characters' => [
                "nully null\x00",
                "nully null�",
            ],
            'ascii with internal NUL control characters' => [
                "nully \x00 null",
                "nully � null",
            ],
            'utf8 truncated at first escape' => [
                "Some partial\xD0",
                "Some partial�",
            ],
            'utf8 with invalid trailing bytes' => [
                "Mozilla/5.0 (compatible; Baiduspider/2.0; +http://www.baidu.com/search/spider.html\xa3\xa9",
                "Mozilla/5.0 (compatible; Baiduspider/2.0; +http://www.baidu.com/search/spider.html��",
            ],
            'utf8 with invalid included bytes' => [
                "Mozilla/5.0 (compatible; Baiduspider/2.0; \xa3\xa9 and more stuff",
                "Mozilla/5.0 (compatible; Baiduspider/2.0; �� and more stuff",
            ],
            'utf8 with invalid leading bytes' => [
                "\xa3\xa9Mozilla/5.0 (compatible; Baiduspider/2.0; and more stuff",
                "��Mozilla/5.0 (compatible; Baiduspider/2.0; and more stuff",
            ],
        ];
    }

    #[DataProvider('provider_printable_utf8')]
    public function test_it_sanitises_to_printable_valid_utf8_string(string $input, ?string $expect = null)
    {
        $this->assertSame(
            $expect ?? $input,
            StringSanitiser::ensurePrintableUtf8($input)
        );
    }

    public static function provider_printable_utf8_max_length(): array
    {
        return [
            'with invalid input, still truncates after cleaning' => [
                "Mozilla/5.0 \x08 and more \xa3\xa9 nonsense",
                [
                    23 => "Mozilla/5.0 � and more ",
                    24 => "Mozilla/5.0 � and more �",
                    25 => "Mozilla/5.0 � and more ��",
                    255 => "Mozilla/5.0 � and more �� nonsense",
                ],
            ],
            'with valid input, truncates as multibyte' => [
                "Hello from Denmark with æøå and stuff",
                [
                    24 => 'Hello from Denmark with ',
                    25 => 'Hello from Denmark with æ',
                    26 => 'Hello from Denmark with æø',
                    27 => 'Hello from Denmark with æøå',
                    28 => 'Hello from Denmark with æøå ',
                    255 => 'Hello from Denmark with æøå and stuff',
                ],
            ],
        ];
    }

    #[DataProvider('provider_printable_utf8_max_length')]
    public function test_it_can_constrain_printable_utf8_to_a_max_length(string $input, array $expected)
    {
        $actual = [];
        foreach (array_keys($expected) as $max_length) {
            $actual[$max_length] = StringSanitiser::ensurePrintableUtf8($input, max_length: $max_length);
        }
        $this->assertSame($expected, $actual);
    }

    public function test_it_does_not_affect_global_substitute_character_state()
    {
        $old_encoding = mb_substitute_character();
        mb_substitute_character(0x3013);
        $input = "whatever \xa3 stuff";
        $encoded_with_default = mb_convert_encoding($input, 'UTF-8', 'UTF-8');
        $this->assertSame('whatever 〓 stuff', $encoded_with_default);
        try {
            $this->assertSame('whatever � stuff', StringSanitiser::ensurePrintableUtf8($input));
            $this->assertSame($encoded_with_default, mb_convert_encoding($input, 'UTF-8', 'UTF-8'));
        } finally {
            mb_substitute_character($old_encoding);
        }
    }

}
