<?php


namespace unit\Ingenerator\PHPUtils\DeploymentConfig;


use Ingenerator\PHPUtils\DeploymentConfig\ConfigValueDecrypter;
use Ingenerator\PHPUtils\DeploymentConfig\InvalidConfigException;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

class ConfigValueDecrypterTest extends TestCase
{
    protected $vfs;

    public function test_it_is_initialisable()
    {
        $this->assertInstanceOf(ConfigValueDecrypter::class, $this->newSubject());
    }

    /**
     * @testWith [0]
     *           [1]
     *           [1.29]
     *           ["anything"]
     *           [{"any": "thing", "other": {"things": "here"}}]
     */
    public function test_it_returns_unchanged_value_if_not_encrypted($value)
    {
        $this->assertSame($value, $this->newSubject()->decrypt($value));
    }

    public function provider_decrypt()
    {
        // A mix of pre-encrypted static values and runtime tests that encrypt / decrypt still works as expected
        $slightly    = 'IbW8swma2gi59n7XQn64gN3EH00ymsZcQaF9/J0Bm1UIXSX92RRCr4idGuAOfz0K0KNQsjXoCAgy/f1YgdcrIQ==';
        $very        = '4dTJ5OoF4qplACrbH0dQqcwn6G37vNlkjBbQWTr+s55OUNmdImpNUxCiO0M22HTOXTmI2LS1xQnWsm4BRxPfdQ==';
        $default     = sodium_crypto_box_keypair();
        $default_pub = sodium_crypto_box_publickey($default);

        return [
            [
                '#SECRET#'.base64_encode(sodium_crypto_box_seal('Hello, world', $default_pub)),
                'Hello, world',
                ['default.secret-config.key' => base64_encode($default)],
            ],
            [
                '#SECRET#'.base64_encode(sodium_crypto_box_seal(159, $default_pub)),
                '159',
                ['default.secret-config.key' => base64_encode($default)],
            ],
            [
                '#SECRET#'.base64_encode(sodium_crypto_box_seal('{"json": "stuff", "int": 159}', $default_pub)),
                '{"json": "stuff", "int": 159}',
                ['default.secret-config.key' => base64_encode($default)],
            ],
            [
                '#SECRET-slightly#p0GWaFlgr7gs2QEqcRayggFHmFqtGhy+04h8/9D+jyrUvup1PtmQUru5pSd0LZ7K52XOSVi86aR7E+808LaRjA+4',
                'Anything goes here',
                ['slightly.secret-config.key' => $slightly],
            ],
            [
                '#SECRET-very#6ntTwBOM+B323wU0LG7FK7bMwz4XpZqdydpc0Ou1eTj50qFAxnn8oJKXuS+72bmy8igKopFKvHHT0/8=',
                'I so privet',
                [
                    'slightly.secret-config.key' => $slightly,
                    'very.secret-config.key'     => $very,
                ],
            ],
        ];
    }

    /**
     * @dataProvider provider_decrypt
     */
    public function test_it_returns_value_decrypted_with_specified_or_default_key($value, $expect, $keypairs)
    {
        $this->vfs = vfsStream::create($keypairs);
        $this->assertSame($expect, $this->newSubject()->decrypt($value));
    }

    public function test_it_recursively_decrypts_values_in_nested_arrays()
    {
        $this->vfs = vfsStream::create(
            [
                'slightly.secret-config.key' => 'IbW8swma2gi59n7XQn64gN3EH00ymsZcQaF9/J0Bm1UIXSX92RRCr4idGuAOfz0K0KNQsjXoCAgy/f1YgdcrIQ==',
                'default.secret-config.key'  => '4dTJ5OoF4qplACrbH0dQqcwn6G37vNlkjBbQWTr+s55OUNmdImpNUxCiO0M22HTOXTmI2LS1xQnWsm4BRxPfdQ==',
            ]
        );
        $this->assertSame(
            [
                'top'   => 'Anything goes here',
                'level' => 'Secret? Me?',
                'more'  => [
                    'stuff' => 'I so privet',
                    'and'   => [
                        'now' => 'This is getting silly',
                    ],
                ],
            ],
            $this->newSubject()->decrypt(
                [
                    'top'   => '#SECRET-slightly#p0GWaFlgr7gs2QEqcRayggFHmFqtGhy+04h8/9D+jyrUvup1PtmQUru5pSd0LZ7K52XOSVi86aR7E+808LaRjA+4',
                    'level' => 'Secret? Me?',
                    'more'  => [
                        'stuff' => '#SECRET#6ntTwBOM+B323wU0LG7FK7bMwz4XpZqdydpc0Ou1eTj50qFAxnn8oJKXuS+72bmy8igKopFKvHHT0/8=',
                        'and'   => [
                            'now' => '#SECRET#KjQMq8qPw3UROS13YbYrq+Ip8kcFMTwLSRhfLoZlyUrCwW+r4rAB5xtfN2gy1vweCC8XLOGpFWBRPUUlzIFksK55Musv',
                        ],
                    ],
                ]
            )
        );
    }

    public function provider_corrupt_key()
    {
        return [
            ['I am not base64!!!'],
            [base64_encode('I am not a valid key!!')],
            // And also, need a keypair not just the private or just the public
            [base64_encode(sodium_crypto_box_publickey(sodium_crypto_box_keypair()))],
            [base64_encode(sodium_crypto_box_secretkey(sodium_crypto_box_keypair()))],
        ];
    }

    /**
     * @dataProvider provider_corrupt_key
     */
    public function test_it_throws_if_required_decryption_key_is_mangled($key)
    {
        $this->vfs = vfsStream::create(['slightly.secret-config.key' => $key]);
        $subject   = $this->newSubject();
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('Corrupt config keypair file');
        $subject->decrypt(
            '#SECRET-slightly#p0GWaFlgr7gs2QEqcRayggFHmFqtGhy+04h8/9D+jyrUvup1PtmQUru5pSd0LZ7K52XOSVi86aR7E+808LaRjA+4'
        );
    }

    public function test_it_throws_if_required_decryption_key_is_not_present()
    {
        $kp                  = sodium_crypto_box_keypair();
        $this->vfs           = vfsStream::create(['slightly.secret-config.key' => base64_encode($kp)]);
        $valid_encrypted_val = sodium_crypto_box_seal('whoops', sodium_crypto_box_publickey($kp));
        // The actual encrypted value is fine, but the problem is that the keypair it specifies isn't present with the
        // correct name.
        $other_keypair_name = uniqid('very');
        $subject            = $this->newSubject();
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('Unknown config decryption key');
        $subject->decrypt("#SECRET-$other_keypair_name#$valid_encrypted_val");
    }

    public function provider_failed_decrypt()
    {
        $lost_key_pub = sodium_crypto_box_publickey(sodium_crypto_box_keypair());

        return [
            [
                // It's just junk, not even base64
                '#SECRET-slightly#wtf is this?',
            ],
            [
                // It's base64 junk
                '#SECRET-slightly#'.base64_encode('No but seriously wtf?'),
            ],
            [
                // Who knows what key this is, it's not the right one
                '#SECRET-slightly#'.base64_encode(sodium_crypto_box_seal('You kiddin me?', $lost_key_pub)),
            ],
        ];
    }

    /**
     * @dataProvider provider_failed_decrypt
     */
    public function test_it_throws_if_decryption_fails($value)
    {
        $this->vfs = vfsStream::create(
            ['slightly.secret-config.key' => 'IbW8swma2gi59n7XQn64gN3EH00ymsZcQaF9/J0Bm1UIXSX92RRCr4idGuAOfz0K0KNQsjXoCAgy/f1YgdcrIQ==']
        );
        $subject   = $this->newSubject();
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('Config decrypt failed');
        $subject->decrypt($value);
    }

    protected function setUp()
    {
        parent::setUp();
        $this->vfs = vfsStream::setup('anywhere');
    }

    protected function newSubject(): ConfigValueDecrypter
    {
        return new ConfigValueDecrypter($this->vfs->url());
    }

}
