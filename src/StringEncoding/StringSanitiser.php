<?php

namespace Ingenerator\PHPUtils\StringEncoding;

class StringSanitiser
{

    /**
     * Sanitises user input to remove invalid UTF8 character sequences and non-printing ASCII (except \n\r\t)
     *
     * Invalid characters are replaced with `?`. Use when you want to silently ignore errors in the input, for example
     * when logging things like user-agents or other non-critical data, rather than causing encoding or database insert
     * errors.
     *
     * @see https://stackoverflow.com/a/57871683/1062943 for original solution by clarkk, updated to allow more whitespace
     *
     * @param string $input
     * @param int|null $max_length
     *
     * @return string
     */
    public static function ensurePrintableUtf8(string $input, ?int $max_length = null): string
    {
        $previous_substitute = mb_substitute_character();
        mb_substitute_character(0xfffd);
        try {
            $cleaned = preg_replace(
                '/[^[:print:]\n\t\r]/u',
                '�',
                mb_convert_encoding($input, 'UTF-8', 'UTF-8')
            );

            if ($max_length !== null) {
                return mb_substr($cleaned, 0, $max_length);
            }

            return $cleaned;
        } finally {
            mb_substitute_character($previous_substitute);
        }
    }

}
