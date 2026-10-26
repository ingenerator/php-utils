<?php

/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 *
 * @licence   BSD-3-Clause
 */

namespace Ingenerator\PHPUtils\Object;

use Closure;
use Ingenerator\PHPUtils\StringEncoding\JSON;
use InvalidArgumentException;

use function array_filter;
use function array_keys;
use function array_values;
use function get_class;
use function property_exists;

class ObjectPropertyPopulator
{
    /**
     * This class is not directly instantiable.
     */
    private function __construct()
    {
    }

    /**
     * @param object $object
     * @param string $property
     */
    public static function assign($object, $property, $value)
    {
        static::assignHash($object, [$property => $value]);
    }

    /**
     * Assign an array of properties on the object keyed by their name, throwing if any property is undefined.
     *
     * @param object $object
     */
    public static function assignHash($object, array $properties)
    {
        if ($undefined = static::listUndefinedProperties($object, array_keys($properties))) {
            throw new InvalidArgumentException(
                'Undefined properties on '.get_class($object).' : '.JSON::encode($undefined, false)
            );
        }

        // This creates an anonymous function with the class scope of the passed object which can then access internal
        // properties as though the method existed within the object's own class definition (therefore including private
        // and protected properties).
        $populate_func = Closure::bind(
            function ($object, array $properties) {
                foreach ($properties as $property => $value) {
                    $object->$property = $value;
                }
            },
            null,
            $object
        );
        $populate_func($object, $properties);
    }

    private static function listUndefinedProperties($object, array $property_names)
    {
        return array_values(
            array_filter(
                $property_names,
                function ($property_name) use ($object) {
                    return !property_exists($object, $property_name);
                }
            )
        );
    }
}
