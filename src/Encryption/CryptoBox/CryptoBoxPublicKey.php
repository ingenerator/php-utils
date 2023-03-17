<?php
declare(strict_types=1);

namespace Ingenerator\PHPUtils\Encryption\CryptoBox;

use Ingenerator\PHPUtils\StringEncoding\Base64Url;
use SensitiveParameter;
use Stringable;
use function preg_match;
use function sodium_crypto_box_seal;
use function sodium_memzero;
use function strlen;
use const SODIUM_CRYPTO_BOX_PUBLICKEYBYTES;

/**
 * Represents the public key of a keypair, together with an identifier to support key rotation
 */
class CryptoBoxPublicKey implements Stringable
{

    public static function fromString(string $exported): static
    {
        if ( ! preg_match('/(?P<keypair_id>[a-z0-9-]+)\.(?P<key_b64>[a-zA-Z0-9_-]+)$/', $exported, $matches)) {
            throw new \InvalidArgumentException('Invalid public key string format');
        }


        return new static(
            public_key: Base64Url::decode($matches['key_b64']),
            // public key is not secret, no need for constant-time encoding
            keypair_id: $matches['keypair_id']
        );
    }

    public function __construct(
        /**
         * The public key in raw binary form
         */
        public readonly string $public_key,
        /**
         * An ID to support key rotation
         */
        public readonly string $keypair_id
    ) {
        if (strlen($this->public_key) !== SODIUM_CRYPTO_BOX_PUBLICKEYBYTES) {
            throw new \InvalidArgumentException('Invalid public key - incorrect length');
        }
    }

    public function encrypt(
        #[SensitiveParameter]
        string $plaintext
    ): CryptoBoxString {
        try {
            return new CryptoBoxString(
                ciphertext: sodium_crypto_box_seal($plaintext, $this->public_key),
                keypair_id: $this->keypair_id
            );
        } finally {
            // Ensure our copy of the sensitive data is cleared from memory
            sodium_memzero($plaintext);
        }
    }

    public function __toString(): string
    {
        // public key is not secret, no need for constant-time encoding
        return $this->keypair_id.'.'.Base64Url::encode($this->public_key);
    }
}
