<?php

namespace Ingenerator\PHPUtils\StringEncoding;

use RuntimeException;

class StringSanitiser
{
    /**
     * Sanitises user input to remove invalid UTF8 character sequences and non-printing ASCII (except \n\r\t).
     *
     * Invalid characters are replaced with `?`. Use when you want to silently ignore errors in the input, for example
     * when logging things like user-agents or other non-critical data, rather than causing encoding or database insert
     * errors.
     *
     * @see https://stackoverflow.com/a/57871683/1062943 for original solution by clarkk, updated to allow more whitespace
     */
    public static function ensurePrintableUtf8(string $input, ?int $max_length = null): string
    {
        $previous_substitute = mb_substitute_character();
        mb_substitute_character(0xFFFD);
        try {
            $cleaned = preg_replace(
                '/[^[:print:]\n\t\r]/u',
                '�',
                mb_convert_encoding($input, 'UTF-8', 'UTF-8')
            );
            if ($cleaned === null) {
                throw new RuntimeException('Failed to replace invalid UTF8 in string');
            }

            if ($max_length !== null) {
                return mb_substr($cleaned, 0, $max_length);
            }

            return $cleaned;
        } finally {
            mb_substitute_character($previous_substitute);
        }
    }
}
