<?php

declare(strict_types=1);

namespace Ingenerator\PHPUtils\ArrayHelpers;

use ArrayAccess;
use ArrayIterator;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use OutOfBoundsException;
use Traversable;

use function array_key_exists;

/**
 * A generic Map collection that prevents overwriting keys or accessing undefined keys.
 *
 * Basically, a strict associative array where each position in the array is readonly (although
 * you can delete an item and then assign a new one).
 *
 * Note that you can still explicitly access it like `$foo = $map['undefined'] ?? 'default'` if
 * you want to access a key that may or may not exist (just like a standard array).
 *
 * @template TValueType
 *
 * @implements ArrayAccess<string, TValueType>
 * @implements IteratorAggregate<string, TValueType>
 */
class UniqueMap implements ArrayAccess, IteratorAggregate, Countable
{
    /**
     * @param array<string,TValueType> $items initial items to put in the map
     */
    public function __construct(private array $items = [])
    {
    }

    /**
     * @param string $offset
     */
    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->items);
    }

    /**
     * @param string $offset
     *
     * @return TValueType
     */
    public function offsetGet(mixed $offset): mixed
    {
        if (!is_string($offset)) {
            throw new InvalidArgumentException('Map keys must be strings');
        }

        if (!array_key_exists($offset, $this->items)) {
            throw new OutOfBoundsException('No item with key '.$offset);
        }

        return $this->items[$offset];
    }

    /**
     * @param string $offset
     * @param TValueType $value
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (!is_string($offset)) {
            throw new InvalidArgumentException('Map keys must be strings');
        }

        if (array_key_exists($offset, $this->items)) {
            throw new DuplicateMapItemException('Map already contains an item with key '.$offset);
        }

        $this->items[$offset] = $value;
    }

    /**
     * @param string $offset
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }

    /** @return ArrayIterator<string, TValueType> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }
}
