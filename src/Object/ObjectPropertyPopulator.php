<?php
/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 * @licence   proprietary
 */

namespace Ingenerator\PHPUtils\Object;


class ObjectPropertyPopulator
{

    /**
     * This class is not directly instantiable
     */
    private function __construct()
    {
    }

    /**
     * @param object $object
     * @param string $property
     * @param mixed  $value
     */
    public static function assign($object, $property, $value)
    {
        static::assignHash($object, [$property => $value]);
    }

    /**
     * Assign an array of properties on the object keyed by their name, throwing if any property is undefined.
     *
     * @param object $object
     * @param array  $properties
     */
    public static function assignHash($object, array $properties)
    {
        if ($undefined = static::listUndefinedProperties($object, array_keys($properties))) {
            throw new \InvalidArgumentException(
                'Undefined properties on '.get_class($object).' : '.json_encode($undefined)
            );
        }

        // This creates an anonymous function with the class scope of the passed object which can then access internal
        // properties as though the method existed within the object's own class definition (therefore including private
        // and protected properties).
        $populate_func = \Closure::bind(
            function ($object, array $properties) {
                foreach ($properties as $property => $value) {
                    $object->$property = $value;
                }
            },
            NULL,
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
                    return ! property_exists($object, $property_name);
                }
            )
        );
    }

}
