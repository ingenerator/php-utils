<?php

/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 *
 * @licence   BSD-3-Clause
 */

namespace Ingenerator\PHPUtils\Object;

use ReflectionClass;

use function strlen;
use function strncmp;

final class ConstantDirectory
{
    private array $constants;

    private function __construct(private readonly string $class_name)
    {
    }

    public static function forClass(string $class): static
    {
        return new self($class);
    }

    /**
     * @return array keyed by the constant id
     */
    public function filterConstants(string $prefix): array
    {
        $prefix_len = strlen($prefix);
        $consts = [];
        foreach ($this->listConstants() as $name => $value) {
            if (strncmp($name, $prefix, $prefix_len) === 0) {
                $consts[$name] = $value;
            }
        }

        return $consts;
    }

    /**
     * @return array keyed by the constant id
     */
    public function listConstants(): array
    {
        if (!isset($this->constants)) {
            $this->constants = $this->loadConstants();
        }

        return $this->constants;
    }

    /**
     * @return array keyed by the constant id
     */
    private function loadConstants(): array
    {
        $consts = [];
        $refl = new ReflectionClass($this->class_name);
        foreach ($refl->getConstants() as $name => $value) {
            $consts[$name] = $value;
        }

        return $consts;
    }
}
