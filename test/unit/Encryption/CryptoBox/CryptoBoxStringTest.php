<?php
declare(strict_types=1);

namespace test\unit\Ingenerator\PHPUtils\Encryption\CryptoBox;

use Ingenerator\PHPUtils\Encryption\CryptoBox\CryptoBoxString;
use Ingenerator\PHPUtils\StringEncoding\Base64Url;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use function random_bytes;

class CryptoBoxStringTest extends TestCase
{
    /*
     * Heavily tested through the tests for CryptoBoxKeypair and CryptoBoxPublicKey
     */

    public function provider_invalid_string_format()
    {
        return [
            'empty' => [''],
            'incorrect format' => ['just a long string'],
            'not base64url' => ['key-id."!&*£&C531()"£&$$$"!£'],
            'no keypair_id' => ['zio0N2IcV1QU637aor-JVG2yrbrY4iuCAE1YiCz6E5j7Y8LeZmysuvLZOgaIeZuQshSjvijpVhHoTSP-qmsfBA'],
        ];
    }

    /**
     * @dataProvider provider_invalid_string_format
     */
    public function test_it_throws_invalid_argument_if_attempting_to_create_from_invalid_string($keypair_string)
    {
        $this->expectException(InvalidArgumentException::class);
        CryptoBoxString::fromString($keypair_string);
    }

    public function test_it_can_be_converted_to_string_encoding_and_recreated()
    {
        $expected = new CryptoBoxString(ciphertext: random_bytes(21), keypair_id: 'b');
        $exported = (string) $expected;

        $this->assertMatchesRegularExpression('/^b\.[a-zA-Z0-9_-]+$/', $exported, 'Should be base64url encoded');

        $this->assertEquals(
            $expected,
            CryptoBoxString::fromString($exported)
        );
    }

    public function test_it_can_provide_just_the_ciphertext_as_base64()
    {
        $original = random_bytes(23);
        $subject = new CryptoBoxString(ciphertext: $original, keypair_id: 'alpha');

        $this->assertSame($original, Base64Url::decode($subject->getCiphertextB64()));
    }

}
