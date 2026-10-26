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

    public static function missingMapValue(string $env): static
    {
        return new self("No config map entry for `$env`, nor one for `*`");
    }
}
