<?php

declare(strict_types=1);

namespace Ingenerator\PHPUtils\Filesystem;

use ErrorException;
use InvalidArgumentException;
use RuntimeException;

use function array_keys;
use function escapeshellarg;
use function exec;
use function uniqid;

class TemporaryDirectoryManager
{
    /**
     * @var bool[]
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
        $path = $this->base_dir.'/'.uniqid($prefix, true);

        try {
            if (!mkdir($path, 0700, true) && !is_dir($path)) {
                throw new ErrorException('Could not make '.$path.': reason unknown');
            }
        } catch (ErrorException $e) {
            throw new RuntimeException('Could not make temporary directory: '.$e->getMessage(), $e->getCode(), $e);
        }

        $this->directories[$path] = true;

        return $path;
    }

    /**
     * Explicitly clean up the managed directory.
     */
    public function cleanup(string $path): void
    {
        if (!isset($this->directories[$path])) {
            throw new InvalidArgumentException('Cannot cleanup `'.$path.'` - not a managed directory');
        }

        $this->removeDirectory($path);
    }

    protected function removeDirectory(string $path): void
    {
        exec('rm -rf '.escapeshellarg($path));
        unset($this->directories[$path]);
    }

    /**
     * Removes all remaining directories.
     */
    public function __destruct()
    {
        foreach (array_keys($this->directories) as $directory) {
            $this->removeDirectory($directory);
        }
    }
}
