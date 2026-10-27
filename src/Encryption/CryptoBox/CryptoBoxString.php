<?php

declare(strict_types=1);

namespace Ingenerator\PHPUtils\Encryption\CryptoBox;

use Ingenerator\PHPUtils\StringEncoding\Base64Url;
use InvalidArgumentException;
use Stringable;

use function preg_match;

final class CryptoBoxString implements Stringable
{
    /**
     * Hydrate from a value that has previously been serialized to a string.
     *
     * @param string $exported of the format '{key_id}.{base64url-encoded-ciphertext}'
     *
     * @see CryptoBoxString::__toString()
     */
    public static function fromString(string $exported): static
    {
        if (!preg_match('/(?P<keypair_id>[a-z0-9-]+)\.(?P<ciphertext_b64>[a-zA-Z0-9_-]+)$/', $exported, $matches)) {
            throw new InvalidArgumentException('Invalid ciphertext string format');
        }

        $ciphertext = Base64Url::decode($matches['ciphertext_b64']);
        if ($ciphertext === false) {
            throw new InvalidArgumentException('Invalid ciphertext encoding');
        }

        return new self(
            ciphertext: $ciphertext,
            // ciphertext is not secret, no need for constant-time encoding
            keypair_id: $matches['keypair_id']
        );
    }

    public function __construct(
        public readonly string $ciphertext,
        public readonly string $keypair_id,
    ) {
    }

    /**
     * Formats as `{key_id}.{base64url-encoded-ciphertext}` for persistence & storage.
     *
     * @see CryptoBoxString::fromString()
     */
    public function __toString(): string
    {
        // ciphertext is not private, no need to use constant-time encoding
        return $this->keypair_id.'.'.Base64Url::encode($this->ciphertext);
    }

    /**
     * Utility method to give a base64 encoded version of the ciphertext.
     */
    public function getCiphertextB64(): string
    {
        // ciphertext is not private, no need to use constant-time encoding
        return Base64Url::encode($this->ciphertext);
    }
}
