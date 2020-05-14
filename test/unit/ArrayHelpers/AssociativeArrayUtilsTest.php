<?php
/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 * @licence   proprietary
 */

namespace test\unit\Ingenerator\PHPUtils\ArrayHelpers;


use Ingenerator\PHPUtils\ArrayHelpers\AssociativeArrayUtils;

class AssociativeArrayUtilsTest extends \PHPUnit\Framework\TestCase
{

    /**
     * @testWith [["one", "two", "three"], false]
     *           [{"1": "mixed indices", "5": "also mixed"}, true]
     *           [{"one": "o clock", "two": "o clock", "three": "o clock"}, true]
     */
    public function test_is_associative(array $array, $expected)
    {
        $this->assertSame(
            $expected,
            AssociativeArrayUtils::isAssociative($array)
        );
    }

    public function provider_paths_to_nested()
    {
        return [
            [
                ['foo' => 'bar'],
                ['foo' => 'bar'],
            ],
            [
                ['foo.bar' => 'baz'],
                ['foo' => ['bar' => 'baz']],
            ],
            [
                ['foo.bar.baz' => 'bil'],
                ['foo' => ['bar' => ['baz' => 'bil']]],
            ],
            [
                [
                    'foo.bar.baz' => 'bil',
                    'foo.bar.ben' => 'bob',
                    'food'        => 'burger',
                ],
                ['foo' => ['bar' => ['baz' => 'bil', 'ben' => 'bob']], 'food' => 'burger'],
            ],
        ];
    }

    /**
     * @dataProvider provider_paths_to_nested
     */
    public function test_it_converts_pathed_hash_to_nested_arrays($input, $expect)
    {
        $this->assertSame($expect, AssociativeArrayUtils::pathsToNested($input));
    }

    public function provider_pop_keys()
    {
        return [
            [
                ['a' => 'apple', 'b' => 'bell', 'c' => 'cookie'],
                ['z'],
                ['popped' => [], 'source' => ['a' => 'apple', 'b' => 'bell', 'c' => 'cookie']],
            ],
            [
                ['a' => 'apple', 'b' => 'bell', 'c' => 'cookie'],
                ['a'],
                ['popped' => ['a' => 'apple'], 'source' => ['b' => 'bell', 'c' => 'cookie']],
            ],
            [
                ['a' => 'apple', 'b' => 'bell', 'c' => 'cookie'],
                ['b', 'c'],
                ['popped' => ['b' => 'bell', 'c' => 'cookie'], 'source' => ['a' => 'apple']],
            ],
            [
                ['a' => 'apple', 'b' => 'bell', 'c' => 'cookie'],
                ['b', 'c', 'a'],
                ['popped' => ['b' => 'bell', 'c' => 'cookie', 'a' => 'apple'], 'source' => []],
            ],
            [
                ['a' => 'apple', 'b' => NULL, 'c' => 'cookie'],
                ['b', 'c'],
                ['popped' => ['b' => NULL, 'c' => 'cookie'], 'source' => ['a' => 'apple']],
            ],
        ];
    }

    /**
     * @dataProvider provider_pop_keys
     */
    public function test_it_can_pop_keys_from_a_source_array($source, $pop_keys, $expect)
    {
        $result = AssociativeArrayUtils::popKeys($source, $pop_keys);
        $this->assertSame($expect, ['popped' => $result, 'source' => $source]);
    }

    /**
     * Provides test data for test_path()
     *
     * @return array
     */
    public function provider_set_path()
    {
        return [
            // Tests returns normal values
            [['foo' => 'bar'], [], 'foo', 'bar'],
            [['kohana' => ['is' => 'awesome']], [], 'kohana.is', 'awesome'],
            [
                ['kohana' => ['is' => 'cool', 'and' => 'slow']],
                ['kohana' => ['is' => 'cool']],
                'kohana.and',
                'slow',
            ],
            // Custom delimiters
            [['kohana' => ['is' => 'awesome']], [], 'kohana/is', 'awesome', '/'],
            // Ensures set_path() casts ints to actual integers for keys
            [['foo' => ['bar']], ['foo' => ['test']], 'foo.0', 'bar'],
            // Tests if it allows arrays
            [['kohana' => ['is' => 'awesome']], [], ['kohana', 'is'], 'awesome'],
        ];
    }

    /**
     * @dataProvider provider_set_path
     */
    public function test_set_path($expected, $array, ...$args)
    {
        AssociativeArrayUtils::setPath($array, ...$args);

        $this->assertSame($expected, $array);
    }


    public function provider_merge()
    {
        return [
            // Test how it merges arrays and sub arrays with assoc keys
            [
                ['name' => 'mary', 'children' => ['fred', 'paul', 'sally', 'jane']],
                ['name' => 'john', 'children' => ['fred', 'paul', 'sally', 'jane']],
                ['name' => 'mary', 'children' => ['jane']],
            ],
            // See how it merges sub-arrays with numerical indexes
            [
                [['test1'], ['test2'], ['test3']],
                [['test1'], ['test2']],
                [['test2'], ['test3']],
            ],
            [
                [[['test1']], [['test2']], [['test3']]],
                [[['test1']], [['test2']]],
                [[['test2']], [['test3']]],
            ],
            [
                ['a' => ['test1', 'test2'], 'b' => ['test2', 'test3']],
                ['a' => ['test1'], 'b' => ['test2']],
                ['a' => ['test2'], 'b' => ['test3']],
            ],
            [
                ['digits' => [0, 1, 2, 3]],
                ['digits' => [0, 1]],
                ['digits' => [2, 3]],
            ],
            // See how it manages merging items with numerical indexes
            [
                [0, 1, 2, 3],
                [0, 1],
                [2, 3],
            ],
            // Try and get it to merge assoc. arrays recursively
            [
                ['foo' => 'bar', ['temp' => 'life']],
                ['foo' => 'bin', ['temp' => 'name']],
                ['foo' => 'bar', ['temp' => 'life']],
            ],
            // Bug #3139
            [
                ['foo' => ['bar']],
                ['foo' => 'bar'],
                ['foo' => ['bar']],
            ],
            [
                ['foo' => 'bar'],
                ['foo' => ['bar']],
                ['foo' => 'bar'],
            ],

            // data set #9
            // Associative, Associative
            [
                ['a' => 'K', 'b' => 'K', 'c' => 'L'],
                ['a' => 'J', 'b' => 'K'],
                ['a' => 'K', 'c' => 'L'],
            ],
            // Associative, Indexed
            [
                ['a' => 'J', 'b' => 'K', 'L'],
                ['a' => 'J', 'b' => 'K'],
                ['K', 'L'],
            ],
            // Associative, Mixed
            [
                ['a' => 'J', 'b' => 'K', 'K', 'c' => 'L'],
                ['a' => 'J', 'b' => 'K'],
                ['K', 'c' => 'L'],
            ],

            // data set #12
            // Indexed, Associative
            [
                ['J', 'K', 'a' => 'K', 'c' => 'L'],
                ['J', 'K'],
                ['a' => 'K', 'c' => 'L'],
            ],
            // Indexed, Indexed
            [
                ['J', 'K', 'L'],
                ['J', 'K'],
                ['K', 'L'],
            ],
            // Indexed, Mixed
            [
                ['K', 'K', 'c' => 'L'],
                ['J', 'K'],
                ['K', 'c' => 'L'],
            ],

            // data set #15
            // Mixed, Associative
            [
                ['a' => 'K', 'K', 'c' => 'L'],
                ['a' => 'J', 'K'],
                ['a' => 'K', 'c' => 'L'],
            ],
            // Mixed, Indexed
            [
                ['a' => 'J', 'K', 'L'],
                ['a' => 'J', 'K'],
                ['J', 'L'],
            ],
            // Mixed, Mixed
            [
                ['a' => 'K', 'L'],
                ['a' => 'J', 'K'],
                ['a' => 'K', 'L'],
            ],
            // Bug #3141
            [
                ['servers' => [['1.1.1.1', 4730], ['2.2.2.2', 4730]]],
                ['servers' => [['1.1.1.1', 4730]]],
                ['servers' => [['2.2.2.2', 4730]]],
            ],
            // Multiple input arrays
            [
                ['a' => 'third', 'b' => [4, 6, 8], 'c' => ['a' => TRUE, 'b' => TRUE, 'c' => TRUE, 'd' => 'last']],
                ['a' => 'first', 'b' => [4], 'c' => ['a' => TRUE]],
                ['a' => 'second', 'b' => [6], 'c' => ['b' => TRUE]],
                ['a' => 'third', 'b' => [8], 'c' => ['c' => TRUE]],
                ['c' => ['d' => 'last']],
            ],
        ];
    }

    /**
     * @dataProvider provider_merge
     */
    public function test_deep_merge($expected, ...$args)
    {
        $this->assertSame(
            $expected,
            AssociativeArrayUtils::deepMerge(...$args)
        );
    }
}
