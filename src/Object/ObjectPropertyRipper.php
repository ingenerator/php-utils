<?php
/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 * @licence   BSD-3-Clause
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

        $props = (self::bindScopedClosure(
            fn() => \get_object_vars($object),
            $object,
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
        static::$rippers[$class] ??= self::bindScopedClosure(
            function ($object, $properties) {
                $values = [];
                foreach ($properties as $property) {
                    $values[$property] = $object->$property;
                }

                return $values;
            },
            $class,
        );

        return static::$rippers[$class];
    }

    /**
     * @param object|class-string<object> $scope
     */
    private static function bindScopedClosure(callable $callback, object|string $scope): \Closure
    {
        $scope_class = \is_object($scope) ? $scope::class : $scope;
        if ($scope_class === \stdClass::class) {
            // Cannot bind to the scope of an internal class (e.g. stdClass), and there is no need to do so since
            // all stdClass properties are public.
            // Note that this is the *not* the case for a user-defined class that extends stdClass, hence checking
            // for the exact class name rather than `instanceof`.
            $scope = null;
        }

        return \Closure::bind($callback, newThis: null, newScope: $scope);
    }
}
