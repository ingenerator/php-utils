<?php
/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 * @licence   proprietary
 */

namespace test\unit\Ingenerator\PHPUtils\Repository;


use Ingenerator\PHPUtils\Object\ObjectPropertyPopulator;
use Ingenerator\PHPUtils\Repository\AbstractArrayRepository;
use PHPUnit\Framework\TestCase;

class AbstractArrayRepositoryTest extends TestCase
{
    public function test_it_is_initialisable_with_no_entities()
    {
        $subject = AnyArrayRepository::withNothing();
        $this->assertInstanceOf(AnyArrayRepository::class, $subject);
        $this->assertSame([], $subject->getEntities());
    }

    public function test_it_is_initialisable_with_single_entity()
    {
        $e       = new AnyEntity;
        $subject = AnyArrayRepository::with($e);
        $this->assertSame([$e], $subject->getEntities());
    }

    public function test_it_is_initialisable_with_array_of_props_to_stub()
    {
        $subject = AnyArrayRepository::with(['prop1' => 'foo']);
        $e       = $subject->getEntities()[0];
        $this->assertInstanceOf(AnyEntity::class, $e);
        $this->assertSame('foo', $e->getProp1());
    }

    public function test_it_is_initialisable_with_multiple_entities_and_arrays()
    {
        $e1       = new AnyEntity();
        $subject  = AnyArrayRepository::with($e1, ['prop1' => 'e2']);
        $entities = $subject->getEntities();
        $this->assertCount(2, $entities);
        $this->assertSame($e1, $entities[0]);
        $this->assertInstanceOf(AnyEntity::class, $entities[1]);
    }

    public function test_it_is_initialisable_with_list_of_multiple_entities()
    {
        $e1      = new AnyEntity;
        $e2      = new AnyEntity;
        $subject = AnyArrayRepository::withList([$e1, $e2]);
        $this->assertSame([$e1, $e2], $subject->getEntities());
    }

    /**
     * @testWith [[], []]
     *           [[{"prop1": "foo"}, {"prop1":"bar"}, {"prop1":"foo"}], {"foo": 2, "bar": 1}]
     */
    public function test_it_provides_base_layer_for_counting_entities_by_group($entities, $expect)
    {
        $subject = AnyArrayRepository::withList($entities);
        $this->assertSame(
            $expect,
            $subject->countWith(function (AnyEntity $e) { return $e->getProp1(); })
        );
    }

    public function test_its_load_with_throws_if_no_entity()
    {
        $subject = AnyArrayRepository::with(['prop1' => 'boo']);
        $this->expectException(\InvalidArgumentException::class);
        $subject->loadWith(function (AnyEntity $e) { return $e->getProp1() === 'baz'; });
    }

    public function test_its_load_with_returns_entity()
    {
        $subject = AnyArrayRepository::with(['prop1' => 'bat'], ['prop1' => 'boo']);
        $e       = $subject->loadWith(function (AnyEntity $e) { return $e->getProp1() === 'boo'; });
        $this->assertSame($subject->getEntities()[1], $e);
    }

    public function test_its_load_with_throws_if_entity_not_unique()
    {
        $subject = AnyArrayRepository::with(['prop1' => 'bat'], ['prop1' => 'boo']);
        $this->expectException(\UnexpectedValueException::class);
        $subject->loadWith(function (AnyEntity $e) { return TRUE; });
    }

    public function test_its_find_with_returns_null_if_no_entity()
    {
        $subject = AnyArrayRepository::with(['prop1' => 'boo']);
        $this->assertNull(
            $subject->findWith(function (AnyEntity $e) { return $e->getProp1() === 'baz'; })
        );
    }

    public function test_its_find_with_returns_entity()
    {
        $subject = AnyArrayRepository::with(['prop1' => 'bat'], ['prop1' => 'boo']);
        $e       = $subject->findWith(function (AnyEntity $e) { return $e->getProp1() === 'boo'; });
        $this->assertSame($subject->getEntities()[1], $e);
    }

    public function test_its_find_with_throws_if_entity_not_unique()
    {
        $subject = AnyArrayRepository::with(['prop1' => 'bat'], ['prop1' => 'boo']);
        $this->expectException(\UnexpectedValueException::class);
        $subject->findWith(function (AnyEntity $e) { return TRUE; });
    }

    /**
     * @testWith [[], []]
     *           [[{"prop1": "ok", "prop2": 0}, {"prop1": "nah-ah", "prop2": 1}, {"prop1": "ok", "prop2": 2}], [0,2]]
     */
    public function test_its_list_with_returns_all_matched_entities($entities, $expect)
    {
        $subject = AnyArrayRepository::withList($entities);
        $this->assertSame(
            $expect,
            \array_map(
                function (AnyEntity $e) { return $e->getProp2(); },
                $subject->listWith(function (AnyEntity $e) { return $e->getProp1() === 'ok'; })
            )
        );
    }

    public function test_its_save_only_adds_new_entities()
    {
        $e1      = new AnyEntity;
        $subject = AnyArrayRepository::with($e1);
        $e2      = new AnyEntity;
        $subject->saveEntity($e2);
        $subject->saveEntity($e1);
        $this->assertSame([$e1, $e2], $subject->getEntities());
    }

    public function test_its_nothing_saved_assertion_passes_when_nothing_saved()
    {
        $subject = AnyArrayRepository::with(['prop1' => 'f']);
        $subject->assertNothingSaved();
    }

    public function test_its_nothing_saved_assertion_fails_if_anything_saved()
    {
        $subject = AnyArrayRepository::withNothing();
        $subject->saveEntity(new AnyEntity);
        $this->assertAssertionFails(
            function () use ($subject) {
                $subject->assertNothingSaved();
            }
        );
    }

    protected function assertAssertionFails($callable)
    {
        try {
            $callable();
        } catch (\RuntimeException $e) {
            // Ignore it this is correct
            return;
        }
        $this->fail('Should fail with an assertion failure');
    }

    public function test_its_saved_only_assertion_passes_when_only_one_object_saved()
    {
        $e1      = new AnyEntity;
        $subject = AnyArrayRepository::withNothing();
        $subject->saveEntity($e1);
        $subject->assertSavedOnly($e1);
    }

    public function test_its_saved_only_fails_if_not_saved()
    {
        $e1      = new AnyEntity;
        $subject = AnyArrayRepository::with($e1);

        $this->assertAssertionFails(
            function () use ($subject, $e1) {
                $subject->assertSavedOnly($e1);
            }
        );
    }

    public function test_its_saved_only_fails_if_different_object_with_same_props_saved()
    {
        $subject = AnyArrayRepository::with(['prop1' => 'foo'], ['prop1' => 'foo']);
        $e1      = $subject->getEntities()[0];
        $e2      = $subject->getEntities()[1];
        $subject->saveEntity($e2);

        $this->assertAssertionFails(
            function () use ($subject, $e1) {
                $subject->assertSavedOnly($e1);
            }
        );
    }

    public function test_its_saved_only_fails_if_object_modified_since_save()
    {
        $e1      = new AnyEntity;
        $subject = AnyArrayRepository::with($e1);
        $subject->saveEntity($e1);

        $e1->setProp2('Bar');

        $this->assertAssertionFails(
            function () use ($subject, $e1) {
                $subject->assertSavedOnly($e1);
            }
        );
    }

}


class AnyArrayRepository extends AbstractArrayRepository
{
    /**
     * @return string
     */
    protected static function getEntityBaseClass()
    {
        return AnyEntity::class;
    }

    /**
     * @return array
     */
    public function getEntities()
    {
        return $this->entities;
    }

    public function assertNothingSaved()
    {
        parent::assertNothingSaved();
    }

    /**
     * @param object $entity
     */
    public function assertSavedOnly($entity)
    {
        parent::assertSavedOnly($entity);
    }

    /**
     * @param callable $callable
     *
     * @return int[]
     */
    public function countWith($callable)
    {
        return parent::countWith($callable);
    }

    /**
     * @param callable $callable
     *
     * @return object
     */
    public function loadWith($callable)
    {
        return parent::loadWith($callable);
    }

    /**
     * @param $callable
     *
     * @return object
     */
    public function findWith($callable)
    {
        return parent::findWith($callable);
    }

    /**
     * @param callable $callable
     *
     * @return object[]
     */
    public function listWith($callable)
    {
        return parent::listWith($callable);
    }

    public function saveEntity($entity)
    {
        parent::saveEntity($entity);
    }


}

class AnyEntity
{
    protected $prop1;
    protected $prop2;

    /**
     * @return mixed
     */
    public function getProp1()
    {
        return $this->prop1;
    }

    /**
     * @return mixed
     */
    public function getProp2()
    {
        return $this->prop2;
    }

    public function setProp2($string)
    {
        $this->prop2 = $string;
    }
}
