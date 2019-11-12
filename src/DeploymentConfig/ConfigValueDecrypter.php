<?php


namespace Ingenerator\PHPUtils\DeploymentConfig;


use Ingenerator\PHPUtils\DeploymentConfig\InvalidConfigException;

class ConfigValueDecrypter
{
    /**
     * @var string
     */
    protected $keypair_dir;

    /**
     * @var string[]
     */
    private $keypairs = [];

    public function __construct(string $keypair_dir)
    {
        $this->keypair_dir = $keypair_dir;
    }

    public function decrypt($value)
    {
        if (is_string($value) AND preg_match('/^#SECRET-?(?P<keypair>.*)#(?P<ciphertext>.+)$/', $value, $matches)) {
            return $this->tryDecrypt($matches['ciphertext'], $this->getKeypair($matches['keypair'] ?: 'default'));
        } elseif (is_array($value)) {
            return array_map([$this, 'decrypt'], $value);
        } else {
            return $value;
        }
    }

    protected function getKeypair(string $name): string
    {
        if ( ! isset($this->keypairs[$name])) {
            $this->keypairs[$name] = $this->loadAndValidateKeypair($name);
        }

        return $this->keypairs[$name];
    }

    protected function tryDecrypt(string $ciphertext, string $keypair): string
    {
        $cipher = base64_decode($ciphertext, TRUE);
        if ($cipher === FALSE) {
            throw InvalidConfigException::decryptFailed('invalid base64');
        }

        $cleartext = sodium_crypto_box_seal_open($cipher, $keypair);
        if ($cleartext === FALSE) {
            throw InvalidConfigException::decryptFailed('corrupt value or incorrect key');
        }

        return $cleartext;
    }

    /**
     * @param string $name
     *
     * @return bool|string
     */
    protected function loadAndValidateKeypair(string $name)
    {
        $keypair_file = sprintf('%s/%s.secret-config.key', $this->keypair_dir, $name);
        if ( ! is_file($keypair_file)) {
            throw InvalidConfigException::missingKeypair($keypair_file);
        }
        $keypair = base64_decode(file_get_contents($keypair_file), TRUE);
        if ($keypair === FALSE) {
            throw InvalidConfigException::corruptKeypair($keypair_file, 'invalid base64');
        }

        try {
            sodium_crypto_box_publickey($keypair);
        } catch (\SodiumException $e) {
            throw InvalidConfigException::corruptKeypair($keypair_file, 'invalid keypair value');
        }

        return $keypair;
    }
}
