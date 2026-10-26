<?php

declare(strict_types=1);

namespace test\unit\Ingenerator\PHPUtils\StringEncoding;

use Ingenerator\PHPUtils\StringEncoding\StringSanitiser;
use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StringSanitiserTest extends TestCase
{
    /**
     * @return Iterator<string, array<string>>
     */
    public static function provider_printable_utf8(): Iterator
    {
        yield 'valid ascii' => [
            'I am valid text',
        ];
        yield 'valid ascii with newlines and tabs' => [
            "I am valid\nmultiline\ttext with whitespace",
        ];
        yield 'valid ascii with windows newlines' => [
            "I am valid\r\nmultiline fróm Windows",
        ];
        yield 'valid UTF8' => [
            'Hello from Denmark with æøå',
        ];
        yield 'ascii with BEL control characters' => [
            "Ring the \x07",
            'Ring the �',
        ];
        yield 'ascii with leading NUL control characters' => [
            "\x00nully null",
            '�nully null',
        ];
        yield 'ascii with trailing NUL control characters' => [
            "nully null\x00",
            'nully null�',
        ];
        yield 'ascii with internal NUL control characters' => [
            "nully \x00 null",
            'nully � null',
        ];
        yield 'utf8 truncated at first escape' => [
            "Some partial\xD0",
            'Some partial�',
        ];
        yield 'utf8 with invalid trailing bytes' => [
            "Mozilla/5.0 (compatible; Baiduspider/2.0; +http://www.baidu.com/search/spider.html\xa3\xa9",
            'Mozilla/5.0 (compatible; Baiduspider/2.0; +http://www.baidu.com/search/spider.html��',
        ];
        yield 'utf8 with invalid included bytes' => [
            "Mozilla/5.0 (compatible; Baiduspider/2.0; \xa3\xa9 and more stuff",
            'Mozilla/5.0 (compatible; Baiduspider/2.0; �� and more stuff',
        ];
        yield 'utf8 with invalid leading bytes' => [
            "\xa3\xa9Mozilla/5.0 (compatible; Baiduspider/2.0; and more stuff",
            '��Mozilla/5.0 (compatible; Baiduspider/2.0; and more stuff',
        ];
    }

    #[DataProvider('provider_printable_utf8')]
    public function testItSanitisesToPrintableValidUtf8String(string $input, ?string $expect = null): void
    {
        $this->assertSame(
            $expect ?? $input,
            StringSanitiser::ensurePrintableUtf8($input)
        );
    }

    /**
     * @return Iterator<string, array<(array<string> | string)>>
     */
    public static function provider_printable_utf8_max_length(): Iterator
    {
        yield 'with invalid input, still truncates after cleaning' => [
            "Mozilla/5.0 \x08 and more \xa3\xa9 nonsense",
            [
                23 => 'Mozilla/5.0 � and more ',
                24 => 'Mozilla/5.0 � and more �',
                25 => 'Mozilla/5.0 � and more ��',
                255 => 'Mozilla/5.0 � and more �� nonsense',
            ],
        ];
        yield 'with valid input, truncates as multibyte' => [
            'Hello from Denmark with æøå and stuff',
            [
                24 => 'Hello from Denmark with ',
                25 => 'Hello from Denmark with æ',
                26 => 'Hello from Denmark with æø',
                27 => 'Hello from Denmark with æøå',
                28 => 'Hello from Denmark with æøå ',
                255 => 'Hello from Denmark with æøå and stuff',
            ],
        ];
    }

    #[DataProvider('provider_printable_utf8_max_length')]
    public function testItCanConstrainPrintableUtf8ToAMaxLength(string $input, array $expected): void
    {
        $actual = [];
        foreach (array_keys($expected) as $max_length) {
            $actual[$max_length] = StringSanitiser::ensurePrintableUtf8($input, max_length: $max_length);
        }
        $this->assertSame($expected, $actual);
    }

    public function testItDoesNotAffectGlobalSubstituteCharacterState(): void
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
