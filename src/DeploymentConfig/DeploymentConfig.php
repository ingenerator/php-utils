<?php

namespace Ingenerator\PHPUtils\DeploymentConfig;

use Ingenerator\PHPUtils\StringEncoding\InvalidJSONException;
use Ingenerator\PHPUtils\StringEncoding\JSON;
use Ingenerator\PHPUtils\Superglobals\ServerGlobal;

final class DeploymentConfig
{
    public const ANY = '*';
    public const STANDALONE = 'standalone';
    public const DEV = 'dev';
    public const CI = 'ci';
    public const PRODUCTION = 'production';
    public const QA = 'qa';
    public const LOADTEST = 'loadtest';

    private string $config_dir = '/etc/ingenerator/conf';

    private readonly string $environment;

    private readonly ConfigMapDeclarationParser $map_parser;

    private readonly ConfigValueDecrypter $value_decrypter;

    /**
     * @return static
     */
    public static function instance()
    {
        static $instance;
        if (!$instance) {
            $instance = new self();
        }

        return $instance;
    }

    private function __construct(
        ServerGlobal $server = new ServerGlobal(),
        ?ConfigValueDecrypter $decrypter = null,
    ) {
        $this->environment = $server->getString('INGENERATOR_ENV') ?? self::PRODUCTION;
        $this->map_parser = new ConfigMapDeclarationParser();
        $this->value_decrypter = $decrypter ?? new ConfigValueDecrypter($this->config_dir.'/app-config-keys');
    }

    public function getEnvironment(): string
    {
        return $this->environment;
    }

    public function isEnvironment(string $environment): bool
    {
        return in_array($this->environment, func_get_args(), true);
    }

    public function notEnvironment(string $environment): bool
    {
        return !in_array($this->environment, func_get_args(), true);
    }

    /**
     * [!!] NOTE: This method does not cache any file reads - you should instead cache the overall result of
     *      loading any configuration file, or wrap this in a caching proxy.
     *
     * [!!] NOTE: This method always returns values as strings - if they are type-sensitive you will instead
     *      need to provision the configs as JSON values and load them with getJson()
     */
    public function read(string $path): ?string
    {
        if ($this->environment === self::STANDALONE) {
            return null;
        }

        $cfg_path = $this->config_dir.'/'.$path;
        if (file_exists($cfg_path)) {
            return $this->value_decrypter->decrypt(file_get_contents($cfg_path));
        }
        throw MissingConfigException::missingFile($path, $cfg_path, $this->environment);
    }

    /**
     * Use this when the value of a config / secret is type-sensitive (integer / boolean / supports null value).
     */
    public function readJSON(string $string): mixed
    {
        $value = $this->read($string);
        if ($value === null) {
            return $value;
        }

        try {
            return JSON::decode($value);
        } catch (InvalidJSONException $e) {
            throw InvalidConfigException::badJSON($string, $e->getMessage());
        }
    }

    /** @param array<mixed> ...$declarations */
    public function map(array ...$declarations): mixed
    {
        $map = $this->map_parser->parse($declarations);
        if (array_key_exists($this->environment, $map)) {
            $value = $map[$this->environment];
        } elseif (array_key_exists(self::ANY, $map)) {
            $value = $map[self::ANY];
        } elseif ($this->environment === self::STANDALONE) {
            return null;
        } else {
            throw MissingConfigException::missingMapValue($this->environment);
        }

        return $this->value_decrypter->decrypt($value);
    }
}
