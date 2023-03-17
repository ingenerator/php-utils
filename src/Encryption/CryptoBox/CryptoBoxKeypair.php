<?php
declare(strict_types=1);

namespace Ingenerator\PHPUtils\Encryption\CryptoBox;

use Ingenerator\TrustIDIngress\ToExtract\SensitiveParameter;
use SodiumException;
use function preg_match;
use function sodium_base642bin;
use function sodium_bin2base64;
use function sodium_crypto_box_keypair;
use function sodium_crypto_box_publickey;
use function sodium_crypto_box_seal_open;
use function sodium_memzero;
use const SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING;

/**
 * Models a secret keypair used to decrypt values with sodium_crypto_box_seal_open
 *
 * Wrapping the keypair in an object allows this instance to be passed around without making copies
 * of the keypair in memory.
 */
class CryptoBoxKeypair
{
    private CryptoBoxPublicKey $public_key;

    /**
     * Create an instance from a string that was previously exported from this library
     *
     * @param string $exported
     *
     * @return static
     */
    public static function fromString(
        #[\SensitiveParameter]
        string $exported
    ): static {
        if ( ! preg_match('/(?P<keypair_id>[a-z0-9-]+)\.(?P<keypair_b64>[a-zA-Z0-9_-]+)$/', $exported, $matches)) {
            throw new \InvalidArgumentException('Invalid keypair string format');
        }

        try {
            // Use constant-time base64 decoding to avoid timing attacks
            $keypair = sodium_base642bin($matches['keypair_b64'], SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);

            return new static(
                keypair: $keypair,
                keypair_id: $matches['keypair_id']
            );
        } finally {
            sodium_memzero($exported);
            if (isset($keypair)) {
                sodium_memzero($keypair);
            }
        }
    }


    /**
     * Randomly generate a new keypair
     *
     * @param string $keypair_id
     *
     * @return static
     * @throws SodiumException
     */
    public static function generate(string $keypair_id): static
    {
        if ( ! preg_match('/^(?P<keypair_id>[a-z0-9-]+)$/', $keypair_id)) {
            throw new \InvalidArgumentException('keypair_id must consist of a-z0-9 only');
        }

        $keypair = sodium_crypto_box_keypair();
        try {
            return new static(
                keypair: $keypair,
                keypair_id: $keypair_id,
            );
        } finally {
            sodium_memzero($keypair);
        }

    }

    /**
     * @param string $keypair The raw binary string for the keypair
     * @param string $keypair_id An application-level identifier for this keypair
     */
    protected function __construct(
        // $keypair can't be readonly because we need to zero the memory on destruct
        #[SensitiveParameter]
        private string $keypair,
        public readonly string $keypair_id
    ) {
        // Creating the public key now is the easiest way to validate that the incoming keypair string is actually valid
        try {
            $this->public_key = new CryptoBoxPublicKey(sodium_crypto_box_publickey($this->keypair), $this->keypair_id);
        } catch (SodiumException $e) {
            throw new \InvalidArgumentException('Invalid keypair: '.$e->getMessage());
        }
    }

    public function __destruct()
    {
        // Ensure that our local copy of the private key is securely removed from memory
        if (isset($this->keypair)) {
            sodium_memzero($this->keypair);
        }
    }

    public function getPublicKey(): CryptoBoxPublicKey
    {
        return $this->public_key;
    }

    /**
     * Decrypt a value that was previously encrypted with the public key
     *
     * @param CryptoBoxString $encrypted
     *
     * @return string
     * @throws DecryptionFailedException if the keypair ID is incorrect, the ciphertext has been tampered with, or value was not encrypted for this keypair
     */
    public function decrypt(CryptoBoxString $encrypted): string
    {
        if ($encrypted->keypair_id !== $this->keypair_id) {
            throw new DecryptionFailedException(
                'Failed to decrypt - incorrect keypair provided'
            );
        }

        try {
            $result = sodium_crypto_box_seal_open($encrypted->ciphertext, $this->keypair);
            if ($result === false) {
                throw new DecryptionFailedException('Failed to decrypt - invalid key or ciphertext?');
            }

            return $result;

        } catch (SodiumException $e) {
            throw new DecryptionFailedException('Failed to decrypt: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Create a string/base64 representation of the keypair.
     *
     * Note, intentionally not using `Stringable` for this because exporting the private keypair should be an explicit
     * action, not something that happens accidentally on a typecast / debug printout etc.
     *
     * @return string
     */
    public function exportToString(): string
    {
        // Use constant-time base64 decoding to avoid timing attacks
        return $this->keypair_id.'.'.sodium_bin2base64($this->keypair, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }

}
