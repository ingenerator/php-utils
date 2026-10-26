<?php

declare(strict_types=1);

namespace test\unit\Ingenerator\PHPUtils\Encryption\CryptoBox;

use Ingenerator\PHPUtils\Encryption\CryptoBox\CryptoBoxKeypair;
use Ingenerator\PHPUtils\Encryption\CryptoBox\CryptoBoxPublicKey;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CryptoBoxPublicKeyTest extends TestCase
{
    /*
     * See also the tests for CryptoBoxKeypair which cover some behaviour of the public key object
     * by testing through the behaviour of the public key.
     */

    public function testItCanBeCreatedFromAKnownStringInputAndUsedToEncrypt()
    {
        $subject = CryptoBoxPublicKey::fromString('some-key.-J5fsjd53SB2lLmTVcGC0k0w59d9E-qBOYVcuDzUpRU');
        $encrypted = $subject->encrypt('open sesame');

        $this->assertSame(
            'open sesame',
            CryptoBoxKeypair::fromString(
                'some-key.waq2hJFuWww5NW7v8XNggBsIp9KOP2MiW1fv6B7URwf4nl-yN3ndIHaUuZNVwYLSTTDn130T6oE5hVy4PNSlFQ'
            )
                ->decrypt($encrypted)
        );
    }

    public static function provider_invalid_keypair_string()
    {
        return [
            'empty' => [''],
            'incorrect format' => ['just a long string'],
            'not base64url' => ['key-id."!&*£&C531()"£&$$$"!£'],
            'no keypair_id' => ['-J5fsjd53SB2lLmTVcGC0k0w59d9E-qBOYVcuDzUpRU'],
            'keypair is too short' => ['some-key.cnViYmlzaA'],
        ];
    }

    #[DataProvider('provider_invalid_keypair_string')]
    public function testItThrowsOnAttemptToCreateFromInvalidString($invalid)
    {
        $this->expectException(InvalidArgumentException::class);
        CryptoBoxPublicKey::fromString($invalid);
    }

    public function testItCanBeConvertedToAUrlsafeBase64StringAndBack()
    {
        $expected = CryptoBoxKeypair::generate('key-one')->getPublicKey();

        $exported = (string) $expected;
        $this->assertMatchesRegularExpression('/^key-one\.[a-zA-Z0-9_-]+$/', $exported, 'Should be base64url encoded');

        $this->assertEquals(
            $expected,
            CryptoBoxPublicKey::fromString($exported)
        );
    }
}
