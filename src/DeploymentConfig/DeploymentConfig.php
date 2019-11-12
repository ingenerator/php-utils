<?php


namespace Ingenerator\PHPUtils\DeploymentConfig;


use Ingenerator\PHPUtils\DeploymentConfig\ConfigMapDeclarationParser;
use Ingenerator\PHPUtils\DeploymentConfig\ConfigValueDecrypter;
use Ingenerator\PHPUtils\DeploymentConfig\InvalidConfigException;
use Ingenerator\PHPUtils\DeploymentConfig\MissingConfigException;

class DeploymentConfig
{
    const ANY        = '*';
    const STANDALONE = 'standalone';
    const DEV        = 'dev';
    const CI         = 'ci';
    const PRODUCTION = 'production';
    const QA         = 'qa';
    const LOADTEST   = 'loadtest';

    /**
     * @var string
     */
    protected $config_dir = '/etc/ingenerator/conf';

    /**
     * @var string
     */
    protected $environment;

    /**
     * @var ConfigMapDeclarationParser
     */
    protected $map_parser;

    /**
     * @var ConfigValueDecrypter
     */
    protected $value_decrypter;

    /**
     * @return static
     */
    public static function instance()
    {
        static $instance;
        if ( ! $instance) {
            $instance = new static;
        }

        return $instance;
    }

    /**
     *
     * @param array|NULL $env_vars - if missing will default to reading from $_SERVER
     */
    protected function __construct(array $env_vars = NULL)
    {
        if ($env_vars === NULL) {
            $env_vars = $_SERVER;
        }
        if (isset($env_vars['INGENERATOR_ENV'])) {
            $this->environment = $env_vars['INGENERATOR_ENV'];
        } else {
            $this->environment = static::PRODUCTION;
        }
        $this->map_parser      = new ConfigMapDeclarationParser;
        $this->value_decrypter = new ConfigValueDecrypter($this->config_dir.'/app-config-keys');
    }

    /**
     * @return string
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * @param string $environment,...
     *
     * @return bool
     */
    public function isEnvironment($environment)
    {
        return in_array($this->environment, func_get_args(), TRUE);
    }

    /**
     * @param string $environment,...
     *
     * @return bool
     */
    public function notEnvironment($environment)
    {
        return ! in_array($this->environment, func_get_args(), TRUE);
    }

    /**
     * [!!] NOTE: This method does not cache any file reads - you should instead cache the overall result of
     *      loading any configuration file, or wrap this in a caching proxy.
     *
     * [!!] NOTE: This method always returns values as strings - if they are type-sensitive you will instead
     *      need to provision the configs as JSON values and load them with getJson()
     *
     * @param string $path
     *
     * @return string
     */
    public function read($path)
    {
        if ($this->environment === static::STANDALONE) {
            return NULL;
        }

        $cfg_path = $this->config_dir.'/'.$path;
        if (file_exists($cfg_path)) {
            return $this->value_decrypter->decrypt(file_get_contents($cfg_path));
        } else {
            throw MissingConfigException::missingFile($path, $cfg_path, $this->environment);
        }
    }

    /**
     * Use this when the value of a config / secret is type-sensitive (integer / boolean / supports null value)
     *
     * @param string $string
     *
     * @return mixed
     */
    public function readJSON($string)
    {
        $value = $this->read($string);
        if ($value === NULL) {
            return $value;
        }

        $result = json_decode($value, TRUE);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $result;
        }

        throw InvalidConfigException::badJSON($string, json_last_error_msg());
    }

    public function map(array ...$declarations)
    {
        if ($this->environment === static::STANDALONE) {
            return NULL;
        }

        $map = $this->map_parser->parse($declarations);
        if (array_key_exists($this->environment, $map)) {
            $value = $map[$this->environment];
        } elseif (array_key_exists(static::ANY, $map)) {
            $value = $map[static::ANY];
        } else {
            throw MissingConfigException::missingMapValue($this->environment);
        }

        return $this->value_decrypter->decrypt($value);
    }
}
