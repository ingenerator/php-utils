<?php

declare(strict_types=1);

namespace unit\Ingenerator\PHPUtils\DeploymentConfig;

use Closure;
use Ingenerator\PHPUtils\DeploymentConfig\ConfigValueDecrypter;
use Ingenerator\PHPUtils\DeploymentConfig\DeploymentConfig;
use Ingenerator\PHPUtils\DeploymentConfig\InvalidConfigException;
use Ingenerator\PHPUtils\DeploymentConfig\MissingConfigException;
use Ingenerator\PHPUtils\Object\ObjectPropertyPopulator;
use Iterator;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class DeploymentConfigTest extends TestCase
{
    private ConfigValueDecrypter $decrypter;

    public function testItsStaticInstanceIsSingleton(): void
    {
        $i = DeploymentConfig::instance();
        $this->assertInstanceOf(DeploymentConfig::class, $i);
        $this->assertSame($i, DeploymentConfig::instance());
    }

    public function testItsEnvironmentDefaultsToProductionIfNotSet(): void
    {
        $subject = $this->newSubject([]);
        $this->assertSame(DeploymentConfig::PRODUCTION, $subject->getEnvironment());
    }

    public function testItsEnvironmentComesFromInjectedArrayIfProvided(): void
    {
        $subject = $this->newSubjectWithEnv(DeploymentConfig::DEV);
        $this->assertSame(DeploymentConfig::DEV, $subject->getEnvironment());
    }

    public function testItsEnvironmentComesFromEnvironmentVariable(): void
    {
        $old_env = $_SERVER['INGENERATOR_ENV'] ?? '~~unset~~';
        try {
            $_SERVER['INGENERATOR_ENV'] = DeploymentConfig::CI;
            $this->assertSame(
                DeploymentConfig::CI,
                $this->newSubjectWithNoArgs()->getEnvironment()
            );
        } finally {
            if ($old_env === '~~unset~~') {
                unset($_SERVER['INGENERATOR_ENV']);
            } else {
                $_SERVER['INGENERATOR_ENV'] = $old_env;
            }
        }
    }

    /**
     * @return Iterator<int, array<(array<string> | bool | string)>>
     */
    public static function provider_is_current_env(): Iterator
    {
        // Simples, single case
        yield [
            DeploymentConfig::PRODUCTION,
            [DeploymentConfig::CI],
            false,
        ];
        yield [
            DeploymentConfig::PRODUCTION,
            [DeploymentConfig::PRODUCTION],
            true,
        ];
        // Match any of the requested ones
        yield [
            DeploymentConfig::DEV,
            [DeploymentConfig::CI, DeploymentConfig::DEV],
            true,
        ];
        yield [
            DeploymentConfig::DEV,
            [
                DeploymentConfig::DEV,
                DeploymentConfig::CI,
            ],
            true,
        ];
        yield [
            DeploymentConfig::DEV,
            [DeploymentConfig::PRODUCTION, DeploymentConfig::CI],
            false,
        ];
        // Randomness
        yield [
            'junk-env',
            [DeploymentConfig::PRODUCTION, DeploymentConfig::DEV],
            false,
        ];
        yield [DeploymentConfig::DEV, ['junk-env'], false];
    }

    #[DataProvider('provider_is_current_env')]
    public function testItsIsEnvironmentReturnsWhetherCurrentEnvironmentOneOfThoseListed(
        string $env,
        array $args,
        bool $expect,
    ): void {
        $subject = $this->newSubjectWithEnv($env);
        $result = call_user_func_array($subject->isEnvironment(...), $args);
        $this->assertSame($expect, $result);
    }

    #[DataProvider('provider_is_current_env')]
    public function testItsNotEnvironmentReturnsWhetherCurrentEnvironmentNoneOfThoseListed(
        string $env,
        array $args,
        bool $expect_is,
    ): void {
        $expect_not = !$expect_is;
        $subject = $this->newSubjectWithEnv($env);
        $result = call_user_func_array($subject->notEnvironment(...), $args);
        $this->assertSame($expect_not, $result);
    }

    #[TestWith(['dev', 'i-am-dev'])]
    #[TestWith(['prod', 'i-am-prod-ish'])]
    #[TestWith(['qa', 'i-am-prod-ish'])]
    #[TestWith(['ci', null])]
    #[TestWith(['imagined', 'who-knows-what-i-am'])]
    #[TestWith(['standalone', 'i-am-standalone'])]
    public function testItsMapReturnsValueOrDefaultForTheCurrentEnv($env, $expect): void
    {
        $subject = $this->newSubjectWithEnv($env);
        $this->assertSame(
            $expect,
            $subject->map(
                ['dev', 'i-am-dev'],
                [['prod', 'qa'], 'i-am-prod-ish'],
                ['ci', null],
                ['standalone', 'i-am-standalone'],
                [DeploymentConfig::ANY, 'who-knows-what-i-am']
            )
        );
    }

    #[TestWith(['dev'])]
    #[TestWith(['standalone'])]
    #[TestWith(['ci'])]
    public function testItsMapReturnsAnyForEnvThatIsNotDefined($env): void
    {
        $subject = $this->newSubjectWithEnv($env);
        $this->assertSame(
            'I am anything',
            $subject->map(
                [DeploymentConfig::ANY, 'I am anything']
            )
        );
    }

    public function testItsMapThrowsIfNoValueDefinedForEnvironment(): void
    {
        $subject = $this->newSubjectWithEnv(DeploymentConfig::QA);
        $this->expectException(MissingConfigException::class);
        $subject->map([DeploymentConfig::PRODUCTION, 'prod']);
    }

    public function testItsMapReturnsNullForStandaloneIfNothingDefined(): void
    {
        $subject = $this->newSubjectWithEnv(DeploymentConfig::STANDALONE);
        $this->assertNull(
            $subject->map(
                [DeploymentConfig::DEV, 'I am dev'],
                [DeploymentConfig::PRODUCTION, 'I am production']
            )
        );
    }

    public function testItsMapDecryptsValues(): void
    {
        $this->decrypter = new PaddedConfigDecryptStub();
        $subject = $this->newSubjectWithEnv(DeploymentConfig::CI);
        $this->assertSame(
            'Iamdecrypted',
            $subject->map(
                [DeploymentConfig::CI, '#SECRET#I a m d e c r y p t e d']
            )
        );
    }

    public function testItsReadAlwaysReturnsNullInStandalone(): void
    {
        $subject = $this->newSubjectWithEnv(DeploymentConfig::STANDALONE);
        $this->assertNull($subject->read('secrets/any/old/secret'));
    }

    public function testItsReadReturnsFileContentIfPresentOutsideStandalone(): void
    {
        $path = $this->givenConfigDirWithFile('secrets/integrations/via/password', 'mypassword');
        $subject = $this->newSubjectWithEnvAndConfigDir(
            DeploymentConfig::DEV,
            $path
        );
        $this->assertSame('mypassword', $subject->read('secrets/integrations/via/password'));
    }

    public function testItsReadSupportsRuntimeDecryption(): void
    {
        $this->decrypter = new PaddedConfigDecryptStub();
        $path = $this->givenConfigDirWithFile(
            'secrets/integrations/via/password',
            '#SECRET#I w a s e n c r y p t e d'
        );
        $subject = $this->newSubjectWithEnvAndConfigDir(
            DeploymentConfig::DEV,
            $path
        );
        $this->assertSame('Iwasencrypted', $subject->read('secrets/integrations/via/password'));
    }

    public function testItsReadThrowsIfFileNotPresentOutsideStandalone(): void
    {
        $path = $this->givenEmptyConfigDir();
        $subject = $this->newSubjectWithEnvAndConfigDir(
            DeploymentConfig::DEV,
            $path
        );
        $this->expectException(MissingConfigException::class);
        $subject->read('secrets/integrations/via/password');
    }

    public function testItsReadJsonAlwaysReturnsNullInStandalone(): void
    {
        $subject = $this->newSubjectWithEnv(DeploymentConfig::STANDALONE);
        $this->assertNull($subject->readJSON('secrets/any/old/secret'));
    }

    #[TestWith([true, true])]
    #[TestWith([null, null])]
    #[TestWith([false, false])]
    #[TestWith([0, 0])]
    #[TestWith(['string', 'string'])]
    #[TestWith([1234, 1234])]
    #[TestWith([1234.5, 1234.5])]
    public function testItsReadJsonReturnsJsonDecodedFileContentIfPresentOutsideStandalone($json, $expect): void
    {
        $path = $this->givenConfigDirWithFile('config/some_scalar', json_encode($json));
        $subject = $this->newSubjectWithEnvAndConfigDir(
            DeploymentConfig::DEV,
            $path
        );
        $this->assertSame($expect, $subject->readJSON('config/some_scalar'));
    }

    public function testItsReadJsonThrowsIfFileNotPresentOutsideStandalone(): void
    {
        $path = $this->givenEmptyConfigDir();
        $subject = $this->newSubjectWithEnvAndConfigDir(
            DeploymentConfig::DEV,
            $path
        );
        $this->expectException(MissingConfigException::class);
        $subject->readJSON('secrets/integrations/via/password');
    }

    public function testItsReadJsonThrowsIfFileJsonInvalidOutsideStandalone(): void
    {
        $path = $this->givenConfigDirWithFile('secrets/database/persistent', 'oh_i_dunno');
        $subject = $this->newSubjectWithEnvAndConfigDir(
            DeploymentConfig::DEV,
            $path
        );
        $this->expectException(InvalidConfigException::class);
        $subject->readJSON('secrets/database/persistent');
    }

    public function testItsReadJsonSupportsRuntimeDecryption(): void
    {
        $this->decrypter = new PaddedConfigDecryptStub();
        $path = $this->givenConfigDirWithFile(
            'secrets/integrations/via/lots',
            '#SECRET#{"f u l l": "fileencrypted"}'
        );
        $subject = $this->newSubjectWithEnvAndConfigDir(DeploymentConfig::DEV, $path);
        $this->assertSame(['full' => 'fileencrypted'], $subject->readJSON('secrets/integrations/via/lots'));
    }

    private function newSubjectWithEnv(string $env): DeploymentConfig
    {
        return $this->newSubject(['INGENERATOR_ENV' => $env]);
    }

    /**
     * @param array<string, string> $env_vars
     */
    private function newSubject(array $env_vars): DeploymentConfig
    {
        $creator = Closure::bind(
            fn (): DeploymentConfig => new DeploymentConfig($env_vars),
            null,
            DeploymentConfig::class
        );

        $subject = $creator();
        ObjectPropertyPopulator::assign($subject, 'value_decrypter', $this->decrypter);

        return $subject;
    }

    private function newSubjectWithNoArgs(): DeploymentConfig
    {
        $creator = Closure::bind(
            fn (): DeploymentConfig => new DeploymentConfig(),
            null,
            DeploymentConfig::class
        );

        return $creator();
    }

    private function givenConfigDirWithFile(string $path, string|bool $content): string
    {
        $vfs = vfsStream::setup('var');
        $cfg_dir = vfsStream::newDirectory('config')
            ->at($vfs);

        $parts = explode('/', $path);
        $filename = array_pop($parts);
        $parent = $cfg_dir;
        foreach ($parts as $path_part) {
            $parent = vfsStream::newDirectory($path_part)->at($parent);
        }

        vfsStream::newFile($filename)
            ->at($parent)
            ->setContent($content);

        return $cfg_dir->url();
    }

    private function givenEmptyConfigDir(): string
    {
        $vfs = vfsStream::setup('var');
        $cfg_dir = vfsStream::newDirectory('config')
            ->at($vfs);

        return $cfg_dir->url();
    }

    private function newSubjectWithEnvAndConfigDir(string $env, string $path): DeploymentConfig
    {
        $subject = $this->newSubjectWithEnv($env);
        ObjectPropertyPopulator::assign($subject, 'config_dir', $path);

        return $subject;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->decrypter = new PaddedConfigDecryptStub();
    }
}

class PaddedConfigDecryptStub extends ConfigValueDecrypter
{
    public function __construct()
    {
    }

    protected function tryDecrypt(string $ciphertext, string $keypair): string
    {
        return str_replace(' ', '', $ciphertext);
    }

    protected function getKeypair(string $name): string
    {
        return 'fake-key';
    }
}
