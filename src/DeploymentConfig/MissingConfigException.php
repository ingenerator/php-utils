<?php

declare(strict_types=1);

namespace Ingenerator\PHPUtils\DeploymentConfig;

use RuntimeException;

class MissingConfigException extends RuntimeException
{
    public static function missingFile($path, $real_path, $env): static
    {
        return new static("Config `$path` is not present in `$real_path` and is required for `$env` environment");
    }

    public static function missingMapValue($env): static
    {
        return new static("No config map entry for `$env`, nor one for `*`");
    }
}
