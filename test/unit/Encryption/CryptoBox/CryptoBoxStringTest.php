<?php

declare(strict_types=1);

namespace test\unit\Ingenerator\PHPUtils\Encryption\CryptoBox;

use Ingenerator\PHPUtils\Encryption\CryptoBox\CryptoBoxString;
use Ingenerator\PHPUtils\StringEncoding\Base64Url;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function random_bytes;

class CryptoBoxStringTest extends TestCase
{
    /*
     * Heavily tested through the tests for CryptoBoxKeypair and CryptoBoxPublicKey
     */

    public static function provider_invalid_string_format()
    {
        return [
            'empty' => [''],
            'incorrect format' => ['just a long string'],
            'not base64url' => ['key-id."!&*£&C531()"£&$$$"!£'],
            'no keypair_id' => ['zio0N2IcV1QU637aor-JVG2yrbrY4iuCAE1YiCz6E5j7Y8LeZmysuvLZOgaIeZuQshSjvijpVhHoTSP-qmsfBA'],
        ];
    }

    #[DataProvider('provider_invalid_string_format')]
    public function testItThrowsInvalidArgumentIfAttemptingToCreateFromInvalidString($keypair_string)
    {
        $this->expectException(InvalidArgumentException::class);
        CryptoBoxString::fromString($keypair_string);
    }

    public function testItCanBeConvertedToStringEncodingAndRecreated()
    {
        $expected = new CryptoBoxString(ciphertext: random_bytes(21), keypair_id: 'b');
        $exported = (string) $expected;

        $this->assertMatchesRegularExpression('/^b\.[a-zA-Z0-9_-]+$/', $exported, 'Should be base64url encoded');

        $this->assertEquals(
            $expected,
            CryptoBoxString::fromString($exported)
        );
    }

    public function testItCanProvideJustTheCiphertextAsBase64()
    {
        $original = random_bytes(23);
        $subject = new CryptoBoxString(ciphertext: $original, keypair_id: 'alpha');

        $this->assertSame($original, Base64Url::decode($subject->getCiphertextB64()));
    }
}
