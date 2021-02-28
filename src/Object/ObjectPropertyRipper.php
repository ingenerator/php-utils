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
     * Grab the values of all properties from inside the object
     *
     * Note it does not support objects where a parent class has any private properties, as these cannot be reliably
     * exported.
     *
     * @param object $object
     *
     * @return array
     */
    public static function ripAll(object $object): array
    {
        // We can't use the cached `ripper` as we don't know in advance what properties to ask for
        // We also shouldn't cache, as individual objects may have variable field names (e.g. with public vars)
        // that are not present on other instances of the same class

        $props = (\Closure::bind(
            fn() => \get_object_vars($object),
            NULL,
            $object
        ))();

        // Safety check - the method above is efficient but can't return private props from parent classes
        // And actually, turning complex classes like that into arrays is probelematic anyway because there's
        // a risk of name collisions between private props at different levels of inheritance. So treat this as an
        // unsupported usage and throw to indicate the data is incomplete.
        $array_vals = (array) $object;
        if (count($array_vals) !== count($props)) {
            throw new \DomainException(
                'Cannot rip all variables from '.\get_class($object).' - does it have inherited private props?'
            );
        }

        return $props;
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
