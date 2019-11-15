<?php

namespace unit\Ingenerator\PHPUtils\DeploymentConfig;

use Closure;
use Ingenerator\PHPUtils\DeploymentConfig\DeploymentConfig;
use Ingenerator\PHPUtils\DeploymentConfig\MissingConfigException;
use Ingenerator\PHPUtils\Object\ObjectPropertyPopulator;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

class DeploymentConfigTest extends TestCase
{

    /**
     * @var ConfigValueDecrypter
     */
    protected $decrypter;

    public function test_its_static_instance_is_singleton()
    {
        $i = DeploymentConfig::instance();
        $this->assertInstanceOf(DeploymentConfig::class, $i);
        $this->assertSame($i, DeploymentConfig::instance());
    }

    public function test_its_environment_defaults_to_production_if_not_set()
    {
        $subject = $this->newSubject([]);
        $this->assertSame(DeploymentConfig::PRODUCTION, $subject->getEnvironment());
    }

    public function test_its_environment_comes_from_injected_array_if_provided()
    {
        $subject = $this->newSubjectWithEnv(DeploymentConfig::DEV);
        $this->assertSame(DeploymentConfig::DEV, $subject->getEnvironment());
    }

    public function test_its_environment_comes_from_environment_variable()
    {
        $old_env = isset($_SERVER['INGENERATOR_ENV']) ? $_SERVER['INGENERATOR_ENV'] : '~~unset~~';
        try {
            $_SERVER['INGENERATOR_ENV'] = DeploymentConfig::CI;
            $this->assertSame(
                DeploymentConfig::CI,
                $this->newSubjectWithNoArgs()->getEnvironment()
            );
        } finally {
            if ($old_env === '~~unset~~') {
                unset ($_SERVER['INGENERATOR_ENV']);
            } else {
                $_SERVER['INGENERATOR_ENV'] = $old_env;
            }
        }
    }

    public function provider_is_current_env()
    {
        return [
            // Simples, single case
            [
                DeploymentConfig::PRODUCTION,
                [DeploymentConfig::CI],
                FALSE,
            ],
            [
                DeploymentConfig::PRODUCTION,
                [DeploymentConfig::PRODUCTION],
                TRUE,
            ],

            // Match any of the requested ones
            [
                DeploymentConfig::DEV,
                [DeploymentConfig::CI, DeploymentConfig::DEV],
                TRUE,
            ],
            [
                DeploymentConfig::DEV,
                [
                    DeploymentConfig::DEV,
                    DeploymentConfig::CI,
                ],
                TRUE,
            ],
            [
                DeploymentConfig::DEV,
                [DeploymentConfig::PRODUCTION, DeploymentConfig::CI],
                FALSE,
            ],

            // Randomness
            [
                'junk-env',
                [DeploymentConfig::PRODUCTION, DeploymentConfig::DEV],
                FALSE,
            ],
            [DeploymentConfig::DEV, ['junk-env'], FALSE],
        ];
    }

    /**
     * @dataProvider provider_is_current_env
     */
    public function test_its_is_environment_returns_whether_current_environment_one_of_those_listed(
        $env,
        $args,
        $expect
    ) {
        $subject = $this->newSubjectWithEnv($env);
        $result  = call_user_func_array([$subject, 'isEnvironment'], $args);
        $this->assertSame($expect, $result);
    }

    /**
     * @dataProvider provider_is_current_env
     */
    public function test_its_not_environment_returns_whether_current_environment_none_of_those_listed(
        $env,
        $args,
        $expect_is
    ) {
        $expect_not = ! $expect_is;
        $subject    = $this->newSubjectWithEnv($env);
        $result     = call_user_func_array([$subject, 'notEnvironment'], $args);
        $this->assertSame($expect_not, $result);
    }

    /**
     * @testWith ["dev", "i-am-dev"]
     *           ["prod", "i-am-prod-ish"]
     *           ["qa", "i-am-prod-ish"]
     *           ["ci", null]
     *           ["imagined", "who-knows-what-i-am"]
     *           ["standalone", "i-am-standalone"]
     */
    public function test_its_map_returns_value_or_default_for_the_current_env($env, $expect)
    {
        $subject = $this->newSubjectWithEnv($env);
        $this->assertSame(
            $expect,
            $subject->map(
                ['dev', 'i-am-dev'],
                [['prod', 'qa'], 'i-am-prod-ish'],
                ['ci', NULL],
                ['standalone', 'i-am-standalone'],
                [DeploymentConfig::ANY, 'who-knows-what-i-am']
            )
        );
    }

    /**
     * @testWith ["dev"]
     *           ["standalone"]
     *           ["ci"]
     */
    public function test_its_map_returns_any_for_env_that_is_not_defined($env)
    {
        $subject = $this->newSubjectWithEnv($env);
        $this->assertSame(
            'I am anything',
            $subject->map(
                [DeploymentConfig::ANY, 'I am anything']
            )
        );
    }

    public function test_its_map_throws_if_no_value_defined_for_environment()
    {
        $subject = $this->newSubjectWithEnv(DeploymentConfig::QA);
        $this->expectException(MissingConfigException::class);
        $subject->map([DeploymentConfig::PRODUCTION, 'prod']);
    }

    public function test_its_map_returns_null_for_standalone_if_nothing_defined()
    {
        $subject = $this->newSubjectWithEnv(DeploymentConfig::STANDALONE);
        $this->assertSame(
            NULL,
            $subject->map(
                [DeploymentConfig::DEV, 'I am dev'],
                [DeploymentConfig::PRODUCTION, 'I am production']
            )
        );
    }

    public function test_its_map_decrypts_values()
    {
        $this->decrypter = new PaddedConfigDecryptStub;
        $subject         = $this->newSubjectWithEnv(DeploymentConfig::CI);
        $this->assertSame(
            'Iamdecrypted',
            $subject->map(
                [DeploymentConfig::CI, '#SECRET#I a m d e c r y p t e d']
            )
        );
    }

    public function test_its_read_always_returns_null_in_standalone()
    {
        $subject = $this->newSubjectWithEnv(DeploymentConfig::STANDALONE);
        $this->assertSame(NULL, $subject->read('secrets/any/old/secret'));
    }

    public function test_its_read_returns_file_content_if_present_outside_standalone()
    {
        $path    = $this->givenConfigDirWithFile('secrets/integrations/via/password', 'mypassword');
        $subject = $this->newSubjectWithEnvAndConfigDir(
            DeploymentConfig::DEV,
            $path
        );
        $this->assertSame('mypassword', $subject->read('secrets/integrations/via/password'));
    }

    public function test_its_read_supports_runtime_decryption()
    {
        $this->decrypter = new PaddedConfigDecryptStub;
        $path            = $this->givenConfigDirWithFile(
            'secrets/integrations/via/password',
            '#SECRET#I w a s e n c r y p t e d'
        );
        $subject         = $this->newSubjectWithEnvAndConfigDir(
            DeploymentConfig::DEV,
            $path
        );
        $this->assertSame('Iwasencrypted', $subject->read('secrets/integrations/via/password'));
    }

    public function test_its_read_throws_if_file_not_present_outside_standalone()
    {
        $path    = $this->givenEmptyConfigDir();
        $subject = $this->newSubjectWithEnvAndConfigDir(
            DeploymentConfig::DEV,
            $path
        );
        $this->expectException(MissingConfigException::class);
        $subject->read('secrets/integrations/via/password');
    }

    public function test_its_read_json_always_returns_null_in_standalone()
    {
        $subject = $this->newSubjectWithEnv(DeploymentConfig::STANDALONE);
        $this->assertSame(NULL, $subject->readJSON('secrets/any/old/secret'));
    }

    /**
     * @testWith ["true", true]
     *           ["null", null]
     *           ["false", false]
     *           ["0", 0]
     *           ["\"string\"", "string"]
     *           ["1234", 1234]
     *           ["1234.5", 1234.5]
     */
    public function test_its_read_json_returns_json_decoded_file_content_if_present_outside_standalone($json, $expect)
    {
        $path    = $this->givenConfigDirWithFile('config/some_scalar', $json);
        $subject = $this->newSubjectWithEnvAndConfigDir(
            DeploymentConfig::DEV,
            $path
        );
        $this->assertSame($expect, $subject->readJSON('config/some_scalar'));
    }

    public function test_its_read_json_throws_if_file_not_present_outside_standalone()
    {
        $path    = $this->givenEmptyConfigDir();
        $subject = $this->newSubjectWithEnvAndConfigDir(
            DeploymentConfig::DEV,
            $path
        );
        $this->expectException(MissingConfigException::class);
        $subject->readJSON('secrets/integrations/via/password');
    }

    public function test_its_read_json_throws_if_file_json_invalid_outside_standalone()
    {
        $path    = $this->givenConfigDirWithFile('secrets/database/persistent', 'oh_i_dunno');
        $subject = $this->newSubjectWithEnvAndConfigDir(
            DeploymentConfig::DEV,
            $path
        );
        $this->expectException(\Ingenerator\PHPUtils\DeploymentConfig\InvalidConfigException::class);
        $subject->readJSON('secrets/database/persistent');
    }

    public function test_its_read_json_supports_runtime_decryption()
    {
        $this->decrypter = new PaddedConfigDecryptStub;
        $path            = $this->givenConfigDirWithFile(
            'secrets/integrations/via/lots',
            '#SECRET#{"f u l l": "fileencrypted"}'
        );
        $subject         = $this->newSubjectWithEnvAndConfigDir(DeploymentConfig::DEV, $path);
        $this->assertSame(['full' => 'fileencrypted'], $subject->readJSON('secrets/integrations/via/lots'));
    }

    /**
     * @param string $env
     *
     * @return DeploymentConfig
     */
    protected function newSubjectWithEnv($env)
    {
        return $this->newSubject(['INGENERATOR_ENV' => $env]);
    }

    /**
     * @param array $env_vars
     *
     * @return DeploymentConfig
     */
    protected function newSubject(array $env_vars)
    {
        $creator = Closure::bind(
            function () use ($env_vars) {
                return new DeploymentConfig($env_vars);
            },
            NULL,
            DeploymentConfig::class
        );

        $subject = $creator();
        ObjectPropertyPopulator::assign($subject, 'value_decrypter', $this->decrypter);

        return $subject;
    }

    /**
     * @return DeploymentConfig
     */
    protected function newSubjectWithNoArgs()
    {
        $creator = Closure::bind(
            function () { return new DeploymentConfig; },
            NULL,
            DeploymentConfig::class
        );

        return $creator();
    }

    protected function givenConfigDirWithFile($path, $content)
    {
        $vfs     = vfsStream::setup('var');
        $cfg_dir = vfsStream::newDirectory('config')
            ->at($vfs);

        $parts    = explode('/', $path);
        $filename = array_pop($parts);
        $parent   = $cfg_dir;
        foreach ($parts as $path_part) {
            $parent = vfsStream::newDirectory($path_part)->at($parent);
        }

        vfsStream::newFile($filename)
            ->at($parent)
            ->setContent($content);

        return $cfg_dir->url();
    }

    protected function givenEmptyConfigDir()
    {
        $vfs     = vfsStream::setup('var');
        $cfg_dir = vfsStream::newDirectory('config')
            ->at($vfs);

        return $cfg_dir->url();
    }

    protected function newSubjectWithEnvAndConfigDir($env, $path)
    {
        $subject = $this->newSubjectWithEnv($env);
        ObjectPropertyPopulator::assign($subject, 'config_dir', $path);

        return $subject;
    }

    protected function setUp()
    {
        parent::setUp();
        $this->decrypter = new PaddedConfigDecryptStub;
    }

}


class PaddedConfigDecryptStub extends \Ingenerator\PHPUtils\DeploymentConfig\ConfigValueDecrypter
{
    public function __construct() { }

    protected function tryDecrypt(string $ciphertext, string $keypair): string
    {
        return str_replace(' ', '', $ciphertext);
    }

    protected function getKeypair(string $name): string
    {
        return 'fake-key';
    }


}
