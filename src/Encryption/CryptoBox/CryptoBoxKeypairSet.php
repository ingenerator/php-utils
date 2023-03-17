<?php
declare(strict_types=1);

namespace Ingenerator\PHPUtils\Encryption\CryptoBox;

use Ingenerator\PHPUtils\ArrayHelpers\UniqueMap;
use OutOfBoundsException;
use SensitiveParameter;
use function sodium_memzero;

/**
 * Represents a set of known keypairs, from which the correct decryption key can be selected
 *
 * Used primarily to support key rotation, load a keypairset with all currently active keys to
 * allow loading both old and new values.
 */
class CryptoBoxKeypairSet
{
    private UniqueMap $keys;

    /**
     * Create from an array of keypair strings previously exported from this library
     *
     * @param string ...$keypair_strings
     *
     * @return static
     */
    public static function fromStrings(
        #[SensitiveParameter]
        string ...$keypair_strings
    ): static {
        $keypairs = [];
        foreach ($keypair_strings as &$kp) {
            try {
                $keypairs[] = CryptoBoxKeypair::fromString($kp);
            } finally {
                // Clear our copy of the keypair string
                sodium_memzero($kp);
            }
        }

        return new static(...$keypairs);
    }

    public function __construct(
        CryptoBoxKeypair ...$keys
    ) {
        $this->keys = new UniqueMap([]);
        foreach ($keys as $key) {
            $this->keys[$key->keypair_id] = $key;
        }
    }

    /**
     * @param string $id
     *
     * @return CryptoBoxKeypair
     * @throws OutOfBoundsException if the key is not loaded
     */
    public function getKeypair(string $id): CryptoBoxKeypair
    {
        return $this->keys[$id];
    }

    /**
     * Pick the correct key & decrypt the value
     *
     * @param CryptoBoxString $encrypted
     *
     * @return string
     * @throws OutOfBoundsException if the key is not loaded
     */
    public function decrypt(CryptoBoxString $encrypted): string
    {
        return $this
            ->getKeypair($encrypted->keypair_id)
            ->decrypt($encrypted);
    }
}
