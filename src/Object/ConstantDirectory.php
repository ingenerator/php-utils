<?php
/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 * @licence   proprietary
 */

namespace Ingenerator\PHPUtils\Object;


class ConstantDirectory
{
    /**
     * @var string
     */
    protected $class_name;

    /**
     * @var array
     */
    protected $constants;

    /**
     * @param string $class_name
     */
    protected function __construct($class_name)
    {
        $this->class_name = $class_name;
    }

    /**
     * @param string $class
     *
     * @return static
     */
    public static function forClass($class)
    {
        return new static($class);
    }

    /**
     * @param string $prefix
     *
     * @return array keyed by the constant id
     */
    public function filterConstants($prefix)
    {
        $prefix_len = \strlen($prefix);
        $consts     = [];
        foreach ($this->listConstants() as $name => $value) {
            if (\strncmp($name, $prefix, $prefix_len) === 0) {
                $consts[$name] = $value;
            }
        }

        return $consts;
    }

    /**
     * @return array keyed by the constant id
     */
    public function listConstants()
    {
        if ($this->constants === NULL) {
            $this->constants = $this->loadConstants();
        }

        return $this->constants;
    }

    /**
     * @return array keyed by the constant id
     */
    protected function loadConstants()
    {
        $consts = [];
        $refl   = new \ReflectionClass($this->class_name);
        foreach ($refl->getConstants() as $name => $value) {
            $consts[$name] = $value;
        }

        return $consts;
    }
}
