<?php

declare(strict_types=1);

namespace test\unit\Ingenerator\PHPUtils\Encryption\CryptoBox;

use Ingenerator\PHPUtils\Encryption\CryptoBox\CryptoBoxKeypair;
use Ingenerator\PHPUtils\Encryption\CryptoBox\CryptoBoxString;
use Ingenerator\PHPUtils\Encryption\CryptoBox\DecryptionFailedException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

use function str_replace;

class CryptoBoxKeypairTest extends TestCase
{
    public function testItCanBeGeneratedAsANewKeypairAndUsedToDecrypt(): void
    {
        $first_keypair = CryptoBoxKeypair::generate('any-id');
        $this->assertSame('any-id', $first_keypair->keypair_id);

        $encrypted = $first_keypair->getPublicKey()->encrypt('any-plain');
        $this->assertSame(
            'any-plain',
            $first_keypair->decrypt($encrypted),
            'Newly generated keypair can encrypt and decrypt'
        );

        // But a newly generated keypair cannot decrypt a value that was decrypted with something else.
        $second_keypair = CryptoBoxKeypair::generate('any-id');
        $this->expectException(DecryptionFailedException::class);
        $second_keypair->decrypt($encrypted);
    }

    #[TestWith(['multi words'])]
    #[TestWith([''])]
    #[TestWith(['Whoopdedo!'])]
    public function testItCannotBeCreatedWithKeypairIdInInvalidFormat($key_id): void
    {
        $this->expectException(InvalidArgumentException::class);
        CryptoBoxKeypair::generate($key_id);
    }

    public function testItCanBeConvertedFromKnownStringInputAndUsedToDecrypt(): void
    {
        $subject = CryptoBoxKeypair::fromString(
            'some-key.zio0N2IcV1QU637aor-JVG2yrbrY4iuCAE1YiCz6E5j7Y8LeZmysuvLZOgaIeZuQshSjvijpVhHoTSP-qmsfBA'
        );
        // To update the test value if required:
        // $enc = $subject->getPublicKey()->encrypt('open sesame');
        // print (string) $enc;
        $this->assertSame(
            'open sesame',
            $subject->decrypt(
                CryptoBoxString::fromString(
                    'some-key._-mdoLEYjGXTx9eZyFDABgjT9ndnC2yvdjh1rshjIARIDdJPzOmkuQWhVxQv0I8oetCKzeOLDbBCzqU'
                )
            )
        );
    }

    public static function provider_invalid_keypair_string(): array
    {
        return [
            'empty' => [''],
            'incorrect format' => ['just a long string'],
            'not base64url' => ['key-id."!&*£&C531()"£&$$$"!£'],
            'no keypair_id' => ['zio0N2IcV1QU637aor-JVG2yrbrY4iuCAE1YiCz6E5j7Y8LeZmysuvLZOgaIeZuQshSjvijpVhHoTSP-qmsfBA'],
            'keypair is too short' => ['some-key.cnViYmlzaA'],
        ];
    }

    #[DataProvider('provider_invalid_keypair_string')]
    public function testItThrowsInvalidArgumentIfAttemptingToCreateFromInvalidString(string $keypair_string): void
    {
        $this->expectException(InvalidArgumentException::class);
        CryptoBoxKeypair::fromString($keypair_string);
    }

    public function testItThrowsDecryptionFailedIfAttemptingToDecryptCryptoBoxStringForDifferentKeyId(): void
    {
        // It doesn't matter that in this example they have the same actual keypair value, they are different key IDs
        // and should be treated as an error to try and decrypt a value with the wrong key
        $kp1 = CryptoBoxKeypair::generate('any-id');
        $enc = $kp1->getPublicKey()->encrypt('any value');

        // Have to really work to force the wrong key ID onto this!
        $keypair_string = str_replace('any-id.', 'wrong-id.', $kp1->exportToString());

        $this->expectException(DecryptionFailedException::class);
        $this->expectExceptionMessage('incorrect key');
        CryptoBoxKeypair::fromString($keypair_string)->decrypt($enc);
    }

    public static function provider_bad_decryption(): array
    {
        return [
            'valid as a key, but not for this message' => [
                CryptoBoxKeypair::generate('a')->exportToString(),
            ],
            // I don't think there actually are any cases where the keypair provided can be invalid, because we
            // validate and throw at the point of construction.
        ];
    }

    #[DataProvider('provider_bad_decryption')]
    public function testItThrowsDecryptionFailedWithIncorrectKey(string $bad_keypair): void
    {
        $encrypted = CryptoBoxKeypair::generate('a')
            ->getPublicKey()
            ->encrypt('i-am-a-secret');

        $subject = CryptoBoxKeypair::fromString($bad_keypair);

        $this->expectException(DecryptionFailedException::class);
        $subject->decrypt($encrypted);
    }

    public function testItThrowsDecryptionFailedIfCiphertextHasBeenTamperedWith(): void
    {
        // Don't need to cover every case, we can trust that libsodium will deal with it, we just need to validate
        // that we are catching & throwing on the sodium failure
        $keypair = CryptoBoxKeypair::generate('a');
        $genuine_msg = $keypair->getPublicKey()->encrypt('sesame');

        $tampered_msg = new CryptoBoxString(
            ciphertext: $genuine_msg->ciphertext.'p', keypair_id: $genuine_msg->keypair_id
        );
        $this->expectException(DecryptionFailedException::class);
        $this->expectExceptionMessage('invalid key');
        $keypair->decrypt($tampered_msg);
    }

    public function testItCanBeConvertedToUrlsafeBase64AndRecreatedWithAllData(): void
    {
        $expected = CryptoBoxKeypair::generate('key-one');
        $exported = $expected->exportToString();
        $this->assertMatchesRegularExpression('/^key-one\.[a-zA-Z0-9_-]+$/', $exported, 'Should be base64url encoded');

        $this->assertEquals(
            $expected,
            CryptoBoxKeypair::fromString($exported)
        );
    }
}
