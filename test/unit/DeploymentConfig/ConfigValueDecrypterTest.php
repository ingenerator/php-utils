<?php

declare(strict_types=1);

namespace unit\Ingenerator\PHPUtils\DeploymentConfig;

use Ingenerator\PHPUtils\DeploymentConfig\ConfigValueDecrypter;
use Ingenerator\PHPUtils\DeploymentConfig\InvalidConfigException;
use Iterator;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

use function base64_encode;
use function uniqid;

final class ConfigValueDecrypterTest extends TestCase
{
    private $vfs;

    public function testItIsInitialisable(): void
    {
        $this->assertInstanceOf(ConfigValueDecrypter::class, $this->newSubject());
    }

    #[TestWith([0])]
    #[TestWith([1])]
    #[TestWith([1.29])]
    #[TestWith(['anything'])]
    #[TestWith([['any' => 'thing', 'other' => ['things' => 'here']]])]
    public function testItReturnsUnchangedValueIfNotEncrypted($value): void
    {
        $this->assertSame($value, $this->newSubject()->decrypt($value));
    }

    /**
     * @return Iterator<int, array<(array<string, string> | string)>>
     */
    public static function provider_decrypt(): Iterator
    {
        // A mix of pre-encrypted static values and runtime tests that encrypt / decrypt still works as expected
        $slightly = 'IbW8swma2gi59n7XQn64gN3EH00ymsZcQaF9/J0Bm1UIXSX92RRCr4idGuAOfz0K0KNQsjXoCAgy/f1YgdcrIQ==';
        $very = '4dTJ5OoF4qplACrbH0dQqcwn6G37vNlkjBbQWTr+s55OUNmdImpNUxCiO0M22HTOXTmI2LS1xQnWsm4BRxPfdQ==';
        $default = sodium_crypto_box_keypair();
        $default_pub = sodium_crypto_box_publickey($default);
        yield [
            '#SECRET#'.base64_encode(sodium_crypto_box_seal('Hello, world', $default_pub)),
            'Hello, world',
            ['default.secret-config.key' => base64_encode($default)],
        ];
        yield [
            '#SECRET#'.base64_encode(sodium_crypto_box_seal('159', $default_pub)),
            '159',
            ['default.secret-config.key' => base64_encode($default)],
        ];
        yield [
            '#SECRET#'.base64_encode(sodium_crypto_box_seal('{"json": "stuff", "int": 159}', $default_pub)),
            '{"json": "stuff", "int": 159}',
            ['default.secret-config.key' => base64_encode($default)],
        ];
        yield [
            '#SECRET-slightly#p0GWaFlgr7gs2QEqcRayggFHmFqtGhy+04h8/9D+jyrUvup1PtmQUru5pSd0LZ7K52XOSVi86aR7E+808LaRjA+4',
            'Anything goes here',
            ['slightly.secret-config.key' => $slightly],
        ];
        yield [
            '#SECRET-very#6ntTwBOM+B323wU0LG7FK7bMwz4XpZqdydpc0Ou1eTj50qFAxnn8oJKXuS+72bmy8igKopFKvHHT0/8=',
            'I so privet',
            [
                'slightly.secret-config.key' => $slightly,
                'very.secret-config.key' => $very,
            ],
        ];
    }

    #[DataProvider('provider_decrypt')]
    public function testItReturnsValueDecryptedWithSpecifiedOrDefaultKey(string $value, string $expect, array $keypairs): void
    {
        $this->vfs = vfsStream::create($keypairs);
        $this->assertSame($expect, $this->newSubject()->decrypt($value));
    }

    public function testItRecursivelyDecryptsValuesInNestedArrays(): void
    {
        $this->vfs = vfsStream::create(
            [
                'slightly.secret-config.key' => 'IbW8swma2gi59n7XQn64gN3EH00ymsZcQaF9/J0Bm1UIXSX92RRCr4idGuAOfz0K0KNQsjXoCAgy/f1YgdcrIQ==',
                'default.secret-config.key' => '4dTJ5OoF4qplACrbH0dQqcwn6G37vNlkjBbQWTr+s55OUNmdImpNUxCiO0M22HTOXTmI2LS1xQnWsm4BRxPfdQ==',
            ]
        );
        $this->assertSame(
            [
                'top' => 'Anything goes here',
                'level' => 'Secret? Me?',
                'more' => [
                    'stuff' => 'I so privet',
                    'and' => [
                        'now' => 'This is getting silly',
                    ],
                ],
            ],
            $this->newSubject()->decrypt(
                [
                    'top' => '#SECRET-slightly#p0GWaFlgr7gs2QEqcRayggFHmFqtGhy+04h8/9D+jyrUvup1PtmQUru5pSd0LZ7K52XOSVi86aR7E+808LaRjA+4',
                    'level' => 'Secret? Me?',
                    'more' => [
                        'stuff' => '#SECRET#6ntTwBOM+B323wU0LG7FK7bMwz4XpZqdydpc0Ou1eTj50qFAxnn8oJKXuS+72bmy8igKopFKvHHT0/8=',
                        'and' => [
                            'now' => '#SECRET#KjQMq8qPw3UROS13YbYrq+Ip8kcFMTwLSRhfLoZlyUrCwW+r4rAB5xtfN2gy1vweCC8XLOGpFWBRPUUlzIFksK55Musv',
                        ],
                    ],
                ]
            )
        );
    }

    /**
     * @return Iterator<int, array<int, string>>
     */
    public static function provider_corrupt_key(): Iterator
    {
        yield ['I am not base64!!!'];
        yield [base64_encode('I am not a valid key!!')];
        // And also, need a keypair not just the private or just the public
        yield [base64_encode(sodium_crypto_box_publickey(sodium_crypto_box_keypair()))];
        yield [base64_encode(sodium_crypto_box_secretkey(sodium_crypto_box_keypair()))];
    }

    #[DataProvider('provider_corrupt_key')]
    public function testItThrowsIfRequiredDecryptionKeyIsMangled(string $key): void
    {
        $this->vfs = vfsStream::create(['slightly.secret-config.key' => $key]);
        $subject = $this->newSubject();
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('Corrupt config keypair file');
        $subject->decrypt(
            '#SECRET-slightly#p0GWaFlgr7gs2QEqcRayggFHmFqtGhy+04h8/9D+jyrUvup1PtmQUru5pSd0LZ7K52XOSVi86aR7E+808LaRjA+4'
        );
    }

    public function testItThrowsIfRequiredDecryptionKeyIsNotPresent(): void
    {
        $kp = sodium_crypto_box_keypair();
        $this->vfs = vfsStream::create(['slightly.secret-config.key' => base64_encode($kp)]);
        $valid_encrypted_val = base64_encode(sodium_crypto_box_seal('whoops', sodium_crypto_box_publickey($kp)));
        // The actual encrypted value is fine, but the problem is that the keypair it specifies isn't present with the
        // correct name.
        $other_keypair_name = uniqid('very');
        $subject = $this->newSubject();
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('Unknown config decryption key');
        $subject->decrypt("#SECRET-$other_keypair_name#$valid_encrypted_val");
    }

    /**
     * @return Iterator<int, array<int, string>>
     */
    public static function provider_failed_decrypt(): Iterator
    {
        $lost_key_pub = sodium_crypto_box_publickey(sodium_crypto_box_keypair());
        yield [
            // It's just junk, not even base64
            '#SECRET-slightly#wtf is this?',
        ];
        yield [
            // It's base64 junk
            '#SECRET-slightly#'.base64_encode('No but seriously wtf?'),
        ];
        yield [
            // Who knows what key this is, it's not the right one
            '#SECRET-slightly#'.base64_encode(sodium_crypto_box_seal('You kiddin me?', $lost_key_pub)),
        ];
    }

    #[DataProvider('provider_failed_decrypt')]
    public function testItThrowsIfDecryptionFails(string $value): void
    {
        $this->vfs = vfsStream::create(
            ['slightly.secret-config.key' => 'IbW8swma2gi59n7XQn64gN3EH00ymsZcQaF9/J0Bm1UIXSX92RRCr4idGuAOfz0K0KNQsjXoCAgy/f1YgdcrIQ==']
        );
        $subject = $this->newSubject();
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('Config decrypt failed');
        $subject->decrypt($value);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->vfs = vfsStream::setup('anywhere');
    }

    protected function newSubject(): ConfigValueDecrypter
    {
        return new ConfigValueDecrypter($this->vfs->url());
    }
}
