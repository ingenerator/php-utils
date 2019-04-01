<?php
/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 * @licence   BSD-3-Clause
 */

namespace Ingenerator\PHPUtils\Repository;

use Doctrine\Common\Collections\Collection;
use Ingenerator\PHPUtils\Object\ObjectPropertyPopulator;
use PHPUnit\Framework\Assert;

/**
 * Base class for an array-based memory repository for use in unit tests. Provides helpers to allow
 * easy and quick creation of array-backed implementations of a project-specific repository interface.
 *
 * @package Ingenerator\PHPUtils\Repository
 */
abstract class AbstractArrayRepository
{
    /**
     * @var array
     */
    protected $entities;

    /**
     * @var string
     */
    protected $save_log;

    protected function __construct(array $entities)
    {
        $this->entities = $entities;
    }

    /**
     * Create a repo with the provided entities. Pass entities, or arrays of properties to stub
     *
     * @param array|object $entity,...
     *
     * @return static
     */
    public static function with($entity)
    {
        return static::withList(\func_get_args());
    }

    /**
     * Same as ::with, but takes an array rather than a list of method parameters
     *
     * @param array[] $entity_data
     *
     * @return static
     */
    public static function withList(array $entity_data)
    {
        $entity_class = static::getEntityBaseClass();
        $entities     = [];
        foreach ($entity_data as $entity) {
            if ( ! $entity instanceof $entity_class) {
                $entity = static::stubEntity($entity);
            }
            $entities[] = $entity;
        }

        return new static($entities);
    }

    /**
     * @return string
     */
    protected static function getEntityBaseClass()
    {
        throw new \BadMethodCallException('Implement your own '.__METHOD__.'!');
    }

    /**
     * @param array $data
     *
     * @return object
     */
    protected static function stubEntity(array $data)
    {
        $class = static::getEntityBaseClass();
        $e     = new $class;
        ObjectPropertyPopulator::assignHash($e, $data);
        return $e;
    }

    /**
     * @return static
     */
    public static function withNothing()
    {
        return new static([]);
    }

    /**
     * Ronseal
     *
     * (Does what it says on the tin)
     */
    protected function assertNothingSaved()
    {
        Assert::assertEquals(
            '',
            $this->save_log,
            'Expected no saved entities'
        );
    }

    /**
     * This, and only this, entity should have been saved
     *
     * @param object $entity
     */
    protected function assertSavedOnly($entity)
    {
        Assert::assertEquals(
            $this->save_log,
            $this->formatSaveLog($entity),
            'Expected entity to be saved exactly once with matching data'
        );
    }

    /**
     * Build a save-log record to allow the class to identify what's been saved for assertions
     *
     * @param object $entity
     *
     * @return string
     */
    protected function formatSaveLog($entity)
    {
        return \sprintf(
            "%s (object %s) with data:\n%s\n",
            \get_class($entity),
            \spl_object_hash($entity),
            \json_encode($this->entityToArray($entity), JSON_PRETTY_PRINT)
        );
    }

    /**
     * Creates a simple array representation of a set of entities that can be formatted as JSON
     *
     * Used to capture a snapshot of entity state at the time it's saved to allow later comparison
     *
     * @param object $entity
     * @param array  $seen_objects
     *
     * @return array
     */
    protected function entityToArray($entity, & $seen_objects = [])
    {
        $entity_hash = \spl_object_hash($entity);
        if (isset($seen_objects[$entity_hash])) {
            return '**RECURSION**';
        } else {
            $seen_objects[$entity_hash] = TRUE;
        }

        $all_props    = \Closure::bind(
            function ($e) {
                return \get_object_vars($e);
            },
            NULL,
            $entity
        );
        $obj_identity = function ($a) {
            return \get_class($a).'#'.\spl_object_hash($a);
        };
        $result       = [];
        foreach ($all_props($entity) as $key => $var) {
            if ( ! \is_object($var)) {
                $result[$key] = $var;
            } elseif ($var instanceof Collection) {
                $result[$key] = [];
                foreach ($var as $collection_item) {
                    $result[$key][] = [
                        $obj_identity($var) => $this->entityToArray($collection_item, $seen_objects)
                    ];
                }
            } elseif ($var instanceof \DateTimeInterface) {
                $result[$key][\get_class($var)] = $var->format(\DateTime::ISO8601);
            } else {
                $result[$key] = [
                    $obj_identity($var) => $this->entityToArray($var, $seen_objects)
                ];
            }
        }

        return $result;
    }

    /**
     * Count entities by a group value returned by the callback
     *
     *     public function countByColour() {
     *       return $this->countWith(
     *         function (MyEntity $e) { return $e->getColour(); }
     *       );
     *     }
     *
     * @param callable $callable
     *
     * @return int[]
     */
    protected function countWith($callable)
    {
        $counts = [];
        foreach ($this->entities as $entity) {
            $group          = \call_user_func($callable, $entity);
            $counts[$group] = isset($counts[$group]) ? ++$counts[$group] : 1;
        }
        return $counts;
    }

    /**
     * Find a single entity matching the callback (throws if non-unique or nothing matching)
     *
     *     public function load($id) {
     *       return $this->loadWith(function (MyEntity $e) use ($id) { return $e->getId() === $id; });
     *     }
     *
     * @param callable $callable
     *
     * @return object
     */
    protected function loadWith($callable)
    {
        if ( ! $entity = $this->findWith($callable)) {
            throw new \InvalidArgumentException('No entity matching criteria');
        }

        return $entity;
    }

    /**
     * Find a single entity matching the callback, or null (throws if non-unique)
     *
     * @param $callable
     *
     * @return object
     */
    protected function findWith($callable)
    {
        $entities = $this->listWith($callable);
        if (\count($entities) > 1) {
            throw new \UnexpectedValueException(
                'Found multiple entities : expected unique condition.'
            );
        }

        return \array_pop($entities);
    }

    /**
     * Find all entities that the callable matches (like array_filter)
     *
     * @param callable $callable
     *
     * @return object[]
     */
    protected function listWith($callable)
    {
        $entities = [];
        foreach ($this->entities as $entity) {
            if (\call_user_func($callable, $entity)) {
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
     * @param object $entity
     */
    protected function saveEntity($entity)
    {
        $this->save_log .= $this->formatSaveLog($entity);
        if ( ! \in_array($entity, $this->entities, TRUE)) {
            $this->entities[] = $entity;
        }
    }

}
