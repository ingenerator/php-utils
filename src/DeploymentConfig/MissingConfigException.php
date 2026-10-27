<?php

declare(strict_types=1);

namespace Ingenerator\PHPUtils\DeploymentConfig;

use RuntimeException;

final class MissingConfigException extends RuntimeException
{
    public static function missingFile(string $path, string $real_path, string $env): static
    {
        return new self("Config `$path` is not present in `$real_path` and is required for `$env` environment");
    }

    public static function failedFileRead(string $path, string $real_path): static
    {
        return new self("Failed to read config `$path` from `$real_path`");
    }

    public static function invalidType(string $path, string $real_path, string $got_type): static
    {
        return new self("Config `$path` in `$real_path` should return a string, got `$got_type`");
    }

    public static function missingMapValue(string $env): static
    {
        return new self("No config map entry for `$env`, nor one for `*`");
    }
}
