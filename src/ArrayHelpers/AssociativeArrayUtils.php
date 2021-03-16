<?php
/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 * @licence   BSD-3-Clause
 */

namespace Ingenerator\PHPUtils\ArrayHelpers;


use function array_key_exists;
use function array_keys;
use function array_shift;
use function count;
use function ctype_digit;
use function explode;
use function in_array;
use function is_array;

class AssociativeArrayUtils
{

    /**
     * Recursively merge two or more arrays. Values in an associative array
     * overwrite previous values with the same key. Values in an indexed array
     * are appended, but only when they do not already exist in the result.
     *
     * Note that this does not work the same as [array_merge_recursive](http://php.net/array_merge_recursive)!
     *
     *     $john = array('name' => 'john', 'children' => array('fred', 'paul', 'sally', 'jane'));
     *     $mary = array('name' => 'mary', 'children' => array('jane'));
     *
     *     // John and Mary are married, merge them together
     *     $john = Arr::merge($john, $mary);
     *
     *     // The output of $john will now be:
     *     array('name' => 'mary', 'children' => array('fred', 'paul', 'sally', 'jane'))
     *
     * Updated PHP7 implementation of Kohana's \Arr::merge, with the same behaviour
     *
     * @param array $merge_base        initial array
     * @param array $merge_patches,... array to merge
     *
     * @return  array
     */
    public static function deepMerge(array $merge_base, array ...$merge_patches)
    {
        foreach ($merge_patches as $patch) {
            if (AssociativeArrayUtils::isAssociative($patch)) {
                foreach ($patch as $key => $value) {
                    if (is_array($value) and is_array($merge_base[$key] ?? NULL)) {
                        $merge_base[$key] = AssociativeArrayUtils::deepMerge($merge_base[$key], $value);
                    } else {
                        $merge_base[$key] = $value;
                    }
                }
            } else {
                foreach ($patch as $value) {
                    if ( ! in_array($value, $merge_base, TRUE)) {
                        $merge_base[] = $value;
                    }
                }
            }
        }

        return $merge_base;
    }

    /**
     * Tests if an array is associative or not.
     *
     *     // Returns TRUE
     *     Arr::is_assoc(array('username' => 'john.doe'));
     *
     *     // Returns FALSE
     *     Arr::is_assoc('foo', 'bar');
     *
     * Imported from the Kohana framework.
     *
     * @param array $array array to check
     *
     * @return  boolean
     *
     * @author Kohana Team
     *
     */
    public static function isAssociative(array $array): bool
    {
        // Keys of the array
        $keys = array_keys($array);

        // If the array keys of the keys match the keys, then the array must
        // not be associative (e.g. the keys array looked like {0:0, 1:1...}).
        return array_keys($keys) !== $keys;
    }

    /**
     * Turns 'this.thing.here => bar' into ['this']['thing']['here'] = bar
     *
     * @param array $input
     *
     * @return array
     */
    public static function pathsToNested(array $input)
    {
        $result = [];
        foreach ($input as $key => $value) {
            AssociativeArrayUtils::setPath($result, $key, $value, '.');
        }

        return $result;
    }

    /**
     * Set a value on an array by path.
     *
     * @param array        $array     Array to update
     * @param string|array $path      Path
     * @param mixed        $value     Value to set
     * @param string       $delimiter Path delimiter
     *
     * @author Kohana Team
     */
    public static function setPath(array &$array, $path, $value, string $delimiter = '.'): void
    {

        // The path has already been separated into keys
        $keys = $path;
        if ( ! is_array($path)) {
            // Split the keys by delimiter
            $keys = explode($delimiter, $path);
        }

        // Set current $array to inner-most array path
        while (count($keys) > 1) {
            $key = array_shift($keys);

            if (ctype_digit($key)) {
                // Make the key an integer
                $key = (int) $key;
            }

            if ( ! isset($array[$key])) {
                $array[$key] = [];
            }

            $array = &$array[$key];
        }

        // Set key on inner-most array
        $array[array_shift($keys)] = $value;
    }

    /**
     * Splits a source array by key - modifies source to remove entries with requested keys, returning removed entries
     *
     * e.g.
     *
     *   $array = ['mix' => 'of', 'things' => 'that', 'might' => 'be', 'useful' => 'separately']
     *   $result = AssociativeArrayUtils::popKeys($array, ['things', 'might', 'undefined']);
     *   assert($array === ['mix' => 'of', 'useful' => 'separately']);
     *   assert($result === ['things' => 'that', 'might' => 'be']); // Note `undefined` not present in source or result
     *
     * @param array $source
     * @param array $pop_keys
     *
     * @return array
     */
    public static function popKeys(array &$source, array $pop_keys): array
    {
        $result = [];
        foreach ($pop_keys as $key) {
            if (isset($source[$key]) or array_key_exists($key, $source)) {
                $result[$key] = $source[$key];
                unset($source[$key]);
            }
        }

        return $result;
    }
}
