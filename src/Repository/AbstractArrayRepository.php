<?php

/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 *
 * @licence   BSD-3-Clause
 */

namespace Ingenerator\PHPUtils\Repository;

use Closure;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\Collection;
use Ingenerator\PHPUtils\Object\ObjectPropertyPopulator;
use Ingenerator\PHPUtils\StringEncoding\JSON;
use InvalidArgumentException;
use PHPUnit\Framework\Assert;
use UnexpectedValueException;
use WeakMap;

use function array_pop;
use function call_user_func;
use function count;
use function func_get_args;
use function get_object_vars;
use function in_array;
use function is_object;
use function spl_object_id;
use function sprintf;

/**
 * Base class for an array-based memory repository for use in unit tests. Provides helpers to allow
 * easy and quick creation of array-backed implementations of a project-specific repository interface.
 *
 * @template T of object
 */
abstract class AbstractArrayRepository
{
    protected string $save_log = '';

    /**
     * @param array<int, T> $entities
     */
    final protected function __construct(protected array $entities)
    {
        $this->onConstruct();
    }

    protected function onConstruct(): void
    {
        // Extension point, to allow us to finalise the constructor to make it safe to do `new static`
    }

    /**
     * Create a repo with the provided entities. Pass entities, or arrays of properties to stub.
     *
     * @param array|T $entity ,...
     *
     * @return static
     */
    public static function with($entity)
    {
        return static::withList(func_get_args());
    }

    /**
     * Same as ::with, but takes an array rather than a list of method parameters.
     *
     * @param list<array|T> $entity_data
     *
     * @return static
     */
    public static function withList(array $entity_data)
    {
        $entity_class = static::getEntityBaseClass();
        $entities = [];
        foreach ($entity_data as $entity) {
            if (!$entity instanceof $entity_class) {
                $entity = static::stubEntity($entity);
            }
            $entities[] = $entity;
        }

        return new static($entities);
    }

    /**
     * @return class-string<T>
     */
    abstract protected static function getEntityBaseClass(): string;

    /**
     * @param array<string, mixed> $data
     *
     * @return T
     */
    protected static function stubEntity(array $data): object
    {
        $class = static::getEntityBaseClass();
        $e = new $class();
        ObjectPropertyPopulator::assignHash($e, $data);

        return $e;
    }

    public static function withNothing(): static
    {
        return new static([]);
    }

    /**
     * Ronseal.
     *
     * (Does what it says on the tin)
     */
    protected function assertNothingSaved(): void
    {
        Assert::assertEquals(
            '',
            $this->save_log,
            'Expected no saved entities',
        );
    }

    /**
     * This, and only this, entity should have been saved.
     *
     * @param T $entity
     */
    protected function assertSavedOnly(object $entity): void
    {
        Assert::assertEquals(
            $this->save_log,
            $this->formatSaveLog($entity),
            'Expected entity to be saved exactly once with matching data',
        );
    }

    /**
     * Build a save-log record to allow the class to identify what's been saved for assertions.
     *
     * @param T $entity
     */
    protected function formatSaveLog(object $entity): string
    {
        return sprintf(
            "%s (object %s) with data:\n%s\n",
            $entity::class,
            spl_object_id($entity),
            JSON::encode($this->entityToArray($entity)),
        );
    }

    /**
     * Creates a simple array representation of a set of entities that can be formatted as JSON.
     *
     * Used to capture a snapshot of entity state at the time it's saved to allow later comparison
     */
    protected function entityToArray(object $entity, ?WeakMap $seen_objects = null): array
    {
        $seen_objects ??= new WeakMap();
        if (isset($seen_objects[$entity])) {
            return '**RECURSION**';
        }
        $seen_objects[$entity] = true;

        $all_props = Closure::bind(
            fn ($e): array => get_object_vars($e),
            null,
            $entity,
        );
        $obj_identity = (fn ($a): string => $a::class.'#'.spl_object_id($a));
        $result = [];
        foreach ($all_props($entity) as $key => $var) {
            if (!is_object($var)) {
                $result[$key] = $var;
            } elseif ($var instanceof Collection) {
                $result[$key] = [];
                foreach ($var as $collection_item) {
                    $result[$key][] = [
                        $obj_identity($var) => $this->entityToArray($collection_item, $seen_objects),
                    ];
                }
            } elseif ($var instanceof DateTimeInterface) {
                $result[$key][$var::class] = $var->format(DateTime::ISO8601);
            } else {
                $result[$key] = [
                    $obj_identity($var) => $this->entityToArray($var, $seen_objects),
                ];
            }
        }

        return $result;
    }

    /**
     * Count entities by a group value returned by the callback.
     *
     *     public function countByColour() {
     *       return $this->countWith(
     *         function (MyEntity $e) { return $e->getColour(); }
     *       );
     *     }
     *
     * @param callable(T):string $callable
     *
     * @return array<string, int>
     */
    protected function countWith(callable $callable): array
    {
        $counts = [];
        foreach ($this->entities as $entity) {
            $group = call_user_func($callable, $entity);
            $counts[$group] = isset($counts[$group]) ? ++$counts[$group] : 1;
        }

        return $counts;
    }

    /**
     * Find a single entity matching the callback (throws if non-unique or nothing matching).
     *
     *     public function load($id) {
     *       return $this->loadWith(function (MyEntity $e) use ($id) { return $e->getId() === $id; });
     *     }
     *
     * @param callable(T):bool $callable
     *
     * @return T
     */
    protected function loadWith(callable $callable): object
    {
        if (!$entity = $this->findWith($callable)) {
            throw new InvalidArgumentException('No entity matching criteria');
        }

        return $entity;
    }

    /**
     * Find a single entity matching the callback, or null (throws if non-unique).
     *
     * @param callable(T):bool $callable
     *
     * @return T|null
     */
    protected function findWith(callable $callable): ?object
    {
        $entities = $this->listWith($callable);
        if (count($entities) > 1) {
            throw new UnexpectedValueException(
                'Found multiple entities : expected unique condition.',
            );
        }

        return array_pop($entities);
    }

    /**
     * Find all entities that the callable matches (like array_filter).
     *
     * @param callable(T):bool $callable
     *
     * @return array<T>
     */
    protected function listWith($callable): array
    {
        $entities = [];
        foreach ($this->entities as $entity) {
            if (call_user_func($callable, $entity)) {
                $entities[] = $entity;
            }
        }

        return $entities;
    }

    /**
     * Use this to implement repository methods that save entities.
     *
     * The state of all entity properties will be captured at time of save to allow verifying that
     * it hasn't been subsequently modified.
     *
     * @param T $entity
     */
    protected function saveEntity($entity): void
    {
        $this->save_log .= $this->formatSaveLog($entity);
        if (!in_array($entity, $this->entities, true)) {
            $this->entities[] = $entity;
        }
    }
}
