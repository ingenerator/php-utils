<?php

declare(strict_types=1);

/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 *
 * @licence   BSD-3-Clause
 */

namespace test\unit\Ingenerator\PHPUtils\ArrayHelpers;

use Ingenerator\PHPUtils\ArrayHelpers\AssociativeArrayUtils;
use InvalidArgumentException;
use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class AssociativeArrayUtilsTest extends TestCase
{
    /** @param array<mixed> $array */
    #[TestWith([['one', 'two', 'three'], false])]
    #[TestWith([[1 => 'mixed indices', 5 => 'also mixed'], true])]
    #[TestWith([['one' => 'o clock', 'two' => 'o clock', 'three' => 'o clock'], true])]
    public function testIsAssociative(array $array, bool $expected): void
    {
        $this->assertSame(
            $expected,
            AssociativeArrayUtils::isAssociative($array)
        );
    }

    /**
     * @return Iterator<int, array<array<string, mixed>>>
     */
    public static function provider_paths_to_nested(): Iterator
    {
        yield [
            ['foo' => 'bar'],
            ['foo' => 'bar'],
        ];
        yield [
            ['foo.bar' => 'baz'],
            ['foo' => ['bar' => 'baz']],
        ];
        yield [
            ['foo.bar.baz' => 'bil'],
            ['foo' => ['bar' => ['baz' => 'bil']]],
        ];
        yield [
            [
                'foo.bar.baz' => 'bil',
                'foo.bar.ben' => 'bob',
                'food' => 'burger',
            ],
            ['foo' => ['bar' => ['baz' => 'bil', 'ben' => 'bob']], 'food' => 'burger'],
        ];
    }

    /**
     * @param array<mixed> $input
     * @param array<mixed> $expect
     */
    #[DataProvider('provider_paths_to_nested')]
    public function testItConvertsPathedHashToNestedArrays(array $input, array $expect): void
    {
        $this->assertSame($expect, AssociativeArrayUtils::pathsToNested($input));
    }

    /**
     * @return Iterator<int, array<array<(int | string), mixed>>>
     */
    public static function provider_pop_keys(): Iterator
    {
        yield [
            ['a' => 'apple', 'b' => 'bell', 'c' => 'cookie'],
            ['z'],
            ['popped' => [], 'source' => ['a' => 'apple', 'b' => 'bell', 'c' => 'cookie']],
        ];
        yield [
            ['a' => 'apple', 'b' => 'bell', 'c' => 'cookie'],
            ['a'],
            ['popped' => ['a' => 'apple'], 'source' => ['b' => 'bell', 'c' => 'cookie']],
        ];
        yield [
            ['a' => 'apple', 'b' => 'bell', 'c' => 'cookie'],
            ['b', 'c'],
            ['popped' => ['b' => 'bell', 'c' => 'cookie'], 'source' => ['a' => 'apple']],
        ];
        yield [
            ['a' => 'apple', 'b' => 'bell', 'c' => 'cookie'],
            ['b', 'c', 'a'],
            ['popped' => ['b' => 'bell', 'c' => 'cookie', 'a' => 'apple'], 'source' => []],
        ];
        yield [
            ['a' => 'apple', 'b' => null, 'c' => 'cookie'],
            ['b', 'c'],
            ['popped' => ['b' => null, 'c' => 'cookie'], 'source' => ['a' => 'apple']],
        ];
    }

    /**
     * @param array<mixed> $source
     * @param list<string> $pop_keys
     * @param array<mixed> $expect
     */
    #[DataProvider('provider_pop_keys')]
    public function testItCanPopKeysFromASourceArray(array $source, array $pop_keys, array $expect): void
    {
        $result = AssociativeArrayUtils::popKeys($source, $pop_keys);
        $this->assertSame($expect, ['popped' => $result, 'source' => $source]);
    }

    /**
     * Provides test data for test_path().
     *
     * @return Iterator<int, array<mixed>>
     */
    public static function provider_set_path(): Iterator
    {
        // Tests returns normal values
        yield [['foo' => 'bar'], [], 'foo', 'bar'];
        yield [['kohana' => ['is' => 'awesome']], [], 'kohana.is', 'awesome'];
        yield [
            ['kohana' => ['is' => 'cool', 'and' => 'slow']],
            ['kohana' => ['is' => 'cool']],
            'kohana.and',
            'slow',
        ];
        // Custom delimiters
        yield [['kohana' => ['is' => 'awesome']], [], 'kohana/is', 'awesome', '/'];
        // Ensures set_path() casts ints to actual integers for keys
        yield [['foo' => ['bar']], ['foo' => ['test']], 'foo.0', 'bar'];
        // Tests if it allows arrays
        yield [['kohana' => ['is' => 'awesome']], [], ['kohana', 'is'], 'awesome'];
    }

    /**
     * @param array<mixed> $expected
     * @param array<mixed> $array
     * @param list<string>|string $path
     */
    #[DataProvider('provider_set_path')]
    public function testSetPath(array $expected, array $array, string|array $path, mixed $value, string $delimiter = '.'): void
    {
        AssociativeArrayUtils::setPath($array, $path, $value, $delimiter);

        $this->assertSame($expected, $array);
    }

    #[TestWith(['foo.things'])]
    #[TestWith(['bil.baz.whatever'])]
    #[TestWith(['bil.baz.boo.bam'])]
    public function testSetPathThrowsOnOverwritingNonArray(string $path): void
    {
        $array = [
            'foo' => 'bar',
            'bil' => [
                'baz' => 15,
            ],
        ];
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot write keys within a non-array path ');
        AssociativeArrayUtils::setPath($array, $path, 'something');
    }

    /**
     * @return Iterator<int, non-empty-list<array<mixed>>>
     */
    public static function provider_merge(): Iterator
    {
        // Test how it merges arrays and sub arrays with assoc keys
        yield [
            ['name' => 'mary', 'children' => ['fred', 'paul', 'sally', 'jane']],
            ['name' => 'john', 'children' => ['fred', 'paul', 'sally', 'jane']],
            ['name' => 'mary', 'children' => ['jane']],
        ];
        // See how it merges sub-arrays with numerical indexes
        yield [
            [['test1'], ['test2'], ['test3']],
            [['test1'], ['test2']],
            [['test2'], ['test3']],
        ];
        yield [
            [[['test1']], [['test2']], [['test3']]],
            [[['test1']], [['test2']]],
            [[['test2']], [['test3']]],
        ];
        yield [
            ['a' => ['test1', 'test2'], 'b' => ['test2', 'test3']],
            ['a' => ['test1'], 'b' => ['test2']],
            ['a' => ['test2'], 'b' => ['test3']],
        ];
        yield [
            ['digits' => [0, 1, 2, 3]],
            ['digits' => [0, 1]],
            ['digits' => [2, 3]],
        ];
        // See how it manages merging items with numerical indexes
        yield [
            [0, 1, 2, 3],
            [0, 1],
            [2, 3],
        ];
        // Try and get it to merge assoc. arrays recursively
        yield [
            ['foo' => 'bar', ['temp' => 'life']],
            ['foo' => 'bin', ['temp' => 'name']],
            ['foo' => 'bar', ['temp' => 'life']],
        ];
        // Bug #3139
        yield [
            ['foo' => ['bar']],
            ['foo' => 'bar'],
            ['foo' => ['bar']],
        ];
        yield [
            ['foo' => 'bar'],
            ['foo' => ['bar']],
            ['foo' => 'bar'],
        ];
        // data set #9
        // Associative, Associative
        yield [
            ['a' => 'K', 'b' => 'K', 'c' => 'L'],
            ['a' => 'J', 'b' => 'K'],
            ['a' => 'K', 'c' => 'L'],
        ];
        // Associative, Indexed
        yield [
            ['a' => 'J', 'b' => 'K', 'L'],
            ['a' => 'J', 'b' => 'K'],
            ['K', 'L'],
        ];
        // Associative, Mixed
        yield [
            ['a' => 'J', 'b' => 'K', 'K', 'c' => 'L'],
            ['a' => 'J', 'b' => 'K'],
            ['K', 'c' => 'L'],
        ];
        // data set #12
        // Indexed, Associative
        yield [
            ['J', 'K', 'a' => 'K', 'c' => 'L'],
            ['J', 'K'],
            ['a' => 'K', 'c' => 'L'],
        ];
        // Indexed, Indexed
        yield [
            ['J', 'K', 'L'],
            ['J', 'K'],
            ['K', 'L'],
        ];
        // Indexed, Mixed
        yield [
            ['K', 'K', 'c' => 'L'],
            ['J', 'K'],
            ['K', 'c' => 'L'],
        ];
        // data set #15
        // Mixed, Associative
        yield [
            ['a' => 'K', 'K', 'c' => 'L'],
            ['a' => 'J', 'K'],
            ['a' => 'K', 'c' => 'L'],
        ];
        // Mixed, Indexed
        yield [
            ['a' => 'J', 'K', 'L'],
            ['a' => 'J', 'K'],
            ['J', 'L'],
        ];
        // Mixed, Mixed
        yield [
            ['a' => 'K', 'L'],
            ['a' => 'J', 'K'],
            ['a' => 'K', 'L'],
        ];
        // Bug #3141
        yield [
            ['servers' => [['1.1.1.1', 4730], ['2.2.2.2', 4730]]],
            ['servers' => [['1.1.1.1', 4730]]],
            ['servers' => [['2.2.2.2', 4730]]],
        ];
        // Multiple input arrays
        yield [
            ['a' => 'third', 'b' => [4, 6, 8], 'c' => ['a' => true, 'b' => true, 'c' => true, 'd' => 'last']],
            ['a' => 'first', 'b' => [4], 'c' => ['a' => true]],
            ['a' => 'second', 'b' => [6], 'c' => ['b' => true]],
            ['a' => 'third', 'b' => [8], 'c' => ['c' => true]],
            ['c' => ['d' => 'last']],
        ];
    }

    /**
     * @param array<mixed> $expected
     * @param array<mixed> ...$args
     */
    #[DataProvider('provider_merge')]
    public function testDeepMerge(array $expected, array ...$args): void
    {
        $this->assertSame(
            $expected,
            AssociativeArrayUtils::deepMerge(...$args),
        );
    }
}
