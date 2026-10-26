<?php

declare(strict_types=1);

namespace Ingenerator\PHPUtils\DeploymentConfig;

use RuntimeException;

final class InvalidConfigException extends RuntimeException
{
    public static function badJSON(string $path, string $error): static
    {
        return new self(
            "Config `$path` contains invalid JSON ($error) - should it be a quoted string?"
        );
    }

    public static function decryptFailed(string $reason): InvalidConfigException
    {
        return new self('Config decrypt failed: '.$reason);
    }

    public static function corruptKeypair(string $keypair_file, string $reason): InvalidConfigException
    {
        return new self('Corrupt config keypair file '.$keypair_file.' ('.$reason.')');
    }

    public static function duplicateEnvironmentMap($env): InvalidConfigException
    {
        return new self('Duplicate environment mapping for '.$env);
    }

    public static function invalidMapDeclaration(string $reason): static
    {
        return new self('Invalid config map declaration: '.$reason);
    }

    public static function missingKeypair(string $keypair_file): InvalidConfigException
    {
        return new self('Unknown config decryption key '.$keypair_file);
    }
}
