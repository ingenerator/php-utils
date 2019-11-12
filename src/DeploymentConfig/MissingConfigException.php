<?php


namespace Ingenerator\PHPUtils\DeploymentConfig;


class MissingConfigException extends \RuntimeException
{

    public static function missingFile($path, $real_path, $env)
    {
        return new static("Config `$path` is not present in `$real_path` and is required for `$env` environment");
    }

    public static function missingMapValue($env)
    {
        return new static("No config map entry for `$env`, nor one for `*`");
    }

}
