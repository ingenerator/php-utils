<?php
declare(strict_types=1);

namespace test\unit\Ingenerator\PHPUtils\Encryption\CryptoBox;

use Ingenerator\PHPUtils\Encryption\CryptoBox\CryptoBoxKeypair;
use Ingenerator\PHPUtils\Encryption\CryptoBox\CryptoBoxString;
use Ingenerator\PHPUtils\Encryption\CryptoBox\DecryptionFailedException;
use Ingenerator\TrustIDIngress\ToExtract\SensitiveParameter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use function str_replace;

class CryptoBoxKeypairTest extends TestCase
{
    public function test_it_can_be_generated_as_a_new_keypair_and_used_to_decrypt()
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

    /**
     * @testWith ["multi words"]
     *           [""]
     *           ["Whoopdedo!"]
     */
    public function test_it_cannot_be_created_with_keypair_id_in_invalid_format($key_id)
    {
        $this->expectException(\InvalidArgumentException::class);
        CryptoBoxKeypair::generate($key_id);
    }

    public function test_it_can_be_converted_from_known_string_input_and_used_to_decrypt()
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

    public function provider_invalid_keypair_string()
    {
        return [
            'empty' => [''],
            'incorrect format' => ['just a long string'],
            'not base64url' => ['key-id."!&*£&C531()"£&$$$"!£'],
            'no keypair_id' => ['zio0N2IcV1QU637aor-JVG2yrbrY4iuCAE1YiCz6E5j7Y8LeZmysuvLZOgaIeZuQshSjvijpVhHoTSP-qmsfBA'],
            'keypair is too short' => ['some-key.cnViYmlzaA'],
        ];
    }

    /**
     * @dataProvider provider_invalid_keypair_string
     */
    public function test_it_throws_invalid_argument_if_attempting_to_create_from_invalid_string($keypair_string)
    {
        $this->expectException(InvalidArgumentException::class);
        CryptoBoxKeypair::fromString($keypair_string);
    }

    public function test_it_throws_decryption_failed_if_attempting_to_decrypt_crypto_box_string_for_different_key_id()
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

    public function provider_bad_decryption()
    {
        return [
            'valid as a key, but not for this message' => [
                CryptoBoxKeypair::generate('a')->exportToString(),
            ],
            // I don't think there actually are any cases where the keypair provided can be invalid, because we
            // validate and throw at the point of construction.
        ];
    }

    /**
     * @dataProvider provider_bad_decryption
     */
    public function test_it_throws_decryption_failed_with_incorrect_key($bad_keypair)
    {
        $encrypted = CryptoBoxKeypair::generate('a')
            ->getPublicKey()
            ->encrypt('i-am-a-secret');

        $subject = CryptoBoxKeypair::fromString($bad_keypair);

        $this->expectException(DecryptionFailedException::class);
        $subject->decrypt($encrypted);
    }

    public function test_it_throws_decryption_failed_if_ciphertext_has_been_tampered_with()
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

    public function test_it_can_be_converted_to_urlsafe_base64_and_recreated_with_all_data()
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
