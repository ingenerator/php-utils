<?php
declare(strict_types=1);

namespace Ingenerator\PHPUtils\Encryption\CryptoBox;

use Ingenerator\PHPUtils\StringEncoding\Base64Url;
use Stringable;
use function preg_match;

class CryptoBoxString implements Stringable
{

    /**
     * Hydrate from a value that has previously been serialized to a string
     *
     * @param string $exported of the format '{key_id}.{base64url-encoded-ciphertext}'
     *
     * @return static
     *
     * @see CryptoBoxString::__toString()
     */
    public static function fromString(string $exported): static
    {
        if ( ! preg_match('/(?P<keypair_id>[a-z0-9-]+)\.(?P<ciphertext_b64>[a-zA-Z0-9_-]+)$/', $exported, $matches)) {
            throw new \InvalidArgumentException('Invalid ciphertext string format');
        }
        
        return new static(
            ciphertext: Base64Url::decode($matches['ciphertext_b64']),
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
     * Formats as `{key_id}.{base64url-encoded-ciphertext}` for persistence & storage
     *
     * @return string
     *
     * @see CryptoBoxString::fromString()
     */
    public function __toString(): string
    {
        // ciphertext is not private, no need to use constant-time encoding
        return $this->keypair_id.'.'.Base64Url::encode($this->ciphertext);
    }

    /**
     * Utility method to give a base64 encoded version of the ciphertext
     *
     * @return string
     */
    public function getCiphertextB64(): string
    {
        // ciphertext is not private, no need to use constant-time encoding
        return Base64Url::encode($this->ciphertext);
    }

}
