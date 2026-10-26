<?php

declare(strict_types=1);

namespace Ingenerator\PHPUtils\Cookie;

use LogicException;

/**
 * Thrown if trying to set a cookie after headers have been sent.
 */
class HeadersSentException extends LogicException
{
    public function __construct(string $cookie_name, string $output_file, ?int $output_line)
    {
        parent::__construct(
            "Cannot assign cookie $cookie_name - headers have been sent by output at $output_file:$output_line"
        );
    }
}
