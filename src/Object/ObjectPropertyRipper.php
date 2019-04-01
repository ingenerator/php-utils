<?php
/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 * @licence   proprietary
 */

namespace Ingenerator\PHPUtils\Object;

/**
 * Rips properties out of an object using local object scope and returns them as an array.
 *
 * @package Ingenerator\Support
 */
class ObjectPropertyRipper
{
    /**
     * @var \Closure[]
     */
    protected static $rippers = [];

    /**
     * Grab the values of all these (presumably private / protected) properties from inside object
     *
     * @param object   $object
     * @param string[] $properties
     *
     * @return array
     */
    public static function rip($object, array $properties)
    {
        $ripper = static::getRipper(\get_class($object));

        return $ripper($object, $properties);
    }

    /**
     * Grab a single property value from the object
     *
     * @param object $object
     * @param string $property
     *
     * @return mixed
     */
    public static function ripOne($object, $property)
    {
        return static::rip($object, [$property])[$property];
    }

    /**
     * Create a callback bound to the scope of the provided class so that it has access to internal
     * properties.
     *
     * @param string $class
     *
     * @return \Closure
     */
    protected static function getRipper($class)
    {
        if ( ! isset(static::$rippers[$class])) {
            static::$rippers[$class] = \Closure::bind(
                function ($object, $properties) {
                    $values = [];
                    foreach ($properties as $property) {
                        $values[$property] = $object->$property;
                    }

                    return $values;
                },
                NULL,
                $class
            );
        }

        return static::$rippers[$class];
    }
}
