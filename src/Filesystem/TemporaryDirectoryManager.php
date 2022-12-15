<?php

namespace Ingenerator\PHPUtils\Filesystem;


use InvalidArgumentException;
use function array_keys;
use function escapeshellarg;

class TemporaryDirectoryManager
{
    /**
     * @var boolean[]
     */
    protected array $directories = [];
    public function __construct(protected string $base_dir)
    {
    }

    /**
     * Create a temporary directory with a unique name under this prefix. The directory
     * will be deleted when the manager is destroyed, if it still exists. Does not include trailing slash!
     */
    public function mkTemp(string $prefix): string
    {
        $path = $this->base_dir.'/'.\uniqid($prefix, TRUE);

        try {
            if ( ! mkdir($path, 0700, TRUE) && ! is_dir($path)) {
                throw new \ErrorException('Could not make '.$path.': reason unknown');
            }
        } catch (\ErrorException $e) {
            throw new \RuntimeException('Could not make temporary directory: '.$e->getMessage());
        }

        $this->directories[$path] = TRUE;

        return $path;
    }

    /**
     * Explicitly clean up the managed directory
     */
    public function cleanup(string $path): void
    {
        if ( ! isset($this->directories[$path])) {
            throw new InvalidArgumentException('Cannot cleanup `'.$path.'` - not a managed directory');
        }

        $this->removeDirectory($path);
    }

    protected function removeDirectory(string $path): void
    {
        \exec('rm -rf '.escapeshellarg($path));
        unset($this->directories[$path]);
    }

    /**
     * Removes all remaining directories
     */
    public function __destruct()
    {
        foreach (array_keys($this->directories) as $directory) {
            $this->removeDirectory($directory);
        }
    }


}
