<?php


namespace Ingenerator\PHPUtils\DeploymentConfig;


class InvalidConfigException extends \RuntimeException
{
    /**
     * @param string $path
     * @param string $error
     *
     * @return InvalidConfigException
     */
    public static function badJSON($path, $error)
    {
        return new static(
            "Config `$path` contains invalid JSON ($error) - should it be a quoted string?"
        );
    }

    public static function decryptFailed(string $reason): InvalidConfigException
    {
        return new static('Config decrypt failed: '.$reason);
    }

    public static function corruptKeypair(string $keypair_file, string $reason): InvalidConfigException
    {
        return new static('Corrupt config keypair file '.$keypair_file.' ('.$reason.')');
    }

    public static function duplicateEnvironmentMap($env): InvalidConfigException
    {
        return new static('Duplicate environment mapping for '.$env);
    }

    public static function invalidMapDeclaration(string $reason)
    {
        return new static('Invalid config map declaration: '.$reason);
    }

    public static function missingKeypair(string $keypair_file): InvalidConfigException
    {
        return new static('Unknown config decryption key '.$keypair_file);
    }
}
