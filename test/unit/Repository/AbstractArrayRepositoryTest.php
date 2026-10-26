<?php

declare(strict_types=1);

/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 *
 * @licence   BSD-3-Clause
 */

namespace test\unit\Ingenerator\PHPUtils\Repository;

use Ingenerator\PHPUtils\Repository\AbstractArrayRepository;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use UnexpectedValueException;

use function array_map;

final class AbstractArrayRepositoryTest extends TestCase
{
    public function testItIsInitialisableWithNoEntities(): void
    {
        $subject = AnyArrayRepository::withNothing();
        $this->assertInstanceOf(AnyArrayRepository::class, $subject);
        $this->assertSame([], $subject->getEntities());
    }

    public function testItIsInitialisableWithSingleEntity(): void
    {
        $e = new AnyEntity();
        $subject = AnyArrayRepository::with($e);
        $this->assertSame([$e], $subject->getEntities());
    }

    public function testItIsInitialisableWithArrayOfPropsToStub(): void
    {
        $subject = AnyArrayRepository::with(['prop1' => 'foo']);
        $e = $subject->getEntities()[0];
        $this->assertInstanceOf(AnyEntity::class, $e);
        $this->assertSame('foo', $e->getProp1());
    }

    public function testItIsInitialisableWithMultipleEntitiesAndArrays(): void
    {
        $e1 = new AnyEntity();
        $subject = AnyArrayRepository::with($e1, ['prop1' => 'e2']);
        $entities = $subject->getEntities();
        $this->assertCount(2, $entities);
        $this->assertSame($e1, $entities[0]);
        $this->assertInstanceOf(AnyEntity::class, $entities[1]);
    }

    public function testItIsInitialisableWithListOfMultipleEntities(): void
    {
        $e1 = new AnyEntity();
        $e2 = new AnyEntity();
        $subject = AnyArrayRepository::withList([$e1, $e2]);
        $this->assertSame([$e1, $e2], $subject->getEntities());
    }

    #[TestWith([[], []])]
    #[TestWith([[['prop1' => 'foo'], ['prop1' => 'bar'], ['prop1' => 'foo']], ['foo' => 2, 'bar' => 1]])]
    public function testItProvidesBaseLayerForCountingEntitiesByGroup($entities, $expect): void
    {
        $subject = AnyArrayRepository::withList($entities);
        $this->assertSame(
            $expect,
            $subject->countWith(fn (AnyEntity $e) => $e->getProp1())
        );
    }

    public function testItsLoadWithThrowsIfNoEntity(): void
    {
        $subject = AnyArrayRepository::with(['prop1' => 'boo']);
        $this->expectException(InvalidArgumentException::class);
        $subject->loadWith(fn (AnyEntity $e): bool => $e->getProp1() === 'baz');
    }

    public function testItsLoadWithReturnsEntity(): void
    {
        $subject = AnyArrayRepository::with(['prop1' => 'bat'], ['prop1' => 'boo']);
        $e = $subject->loadWith(fn (AnyEntity $e): bool => $e->getProp1() === 'boo');
        $this->assertSame($subject->getEntities()[1], $e);
    }

    public function testItsLoadWithThrowsIfEntityNotUnique(): void
    {
        $subject = AnyArrayRepository::with(['prop1' => 'bat'], ['prop1' => 'boo']);
        $this->expectException(UnexpectedValueException::class);
        $subject->loadWith(fn (AnyEntity $e): true => true);
    }

    public function testItsFindWithReturnsNullIfNoEntity(): void
    {
        $subject = AnyArrayRepository::with(['prop1' => 'boo']);
        $this->assertNull(
            $subject->findWith(fn (AnyEntity $e): bool => $e->getProp1() === 'baz')
        );
    }

    public function testItsFindWithReturnsEntity(): void
    {
        $subject = AnyArrayRepository::with(['prop1' => 'bat'], ['prop1' => 'boo']);
        $e = $subject->findWith(fn (AnyEntity $e): bool => $e->getProp1() === 'boo');
        $this->assertSame($subject->getEntities()[1], $e);
    }

    public function testItsFindWithThrowsIfEntityNotUnique(): void
    {
        $subject = AnyArrayRepository::with(['prop1' => 'bat'], ['prop1' => 'boo']);
        $this->expectException(UnexpectedValueException::class);
        $subject->findWith(fn (AnyEntity $e): true => true);
    }

    #[TestWith([[], []])]
    #[TestWith([[['prop1' => 'ok', 'prop2' => 0], ['prop1' => 'nah-ah', 'prop2' => 1], ['prop1' => 'ok', 'prop2' => 2]], [0, 2]])]
    public function testItsListWithReturnsAllMatchedEntities($entities, $expect): void
    {
        $subject = AnyArrayRepository::withList($entities);
        $this->assertSame(
            $expect,
            array_map(
                fn (AnyEntity $e) => $e->getProp2(),
                $subject->listWith(fn (AnyEntity $e): bool => $e->getProp1() === 'ok')
            )
        );
    }

    public function testItsSaveOnlyAddsNewEntities(): void
    {
        $e1 = new AnyEntity();
        $subject = AnyArrayRepository::with($e1);
        $e2 = new AnyEntity();
        $subject->saveEntity($e2);
        $subject->saveEntity($e1);
        $this->assertSame([$e1, $e2], $subject->getEntities());
    }

    public function testItsNothingSavedAssertionPassesWhenNothingSaved(): void
    {
        $subject = AnyArrayRepository::with(['prop1' => 'f']);
        $subject->assertNothingSaved();
    }

    public function testItsNothingSavedAssertionFailsIfAnythingSaved(): void
    {
        $subject = AnyArrayRepository::withNothing();
        $subject->saveEntity(new AnyEntity());
        $this->assertAssertionFails(
            function () use ($subject): void {
                $subject->assertNothingSaved();
            }
        );
    }

    protected function assertAssertionFails($callable): void
    {
        try {
            $callable();
        } catch (RuntimeException) {
            // Ignore it this is correct
            return;
        }
        $this->fail('Should fail with an assertion failure');
    }

    public function testItsSavedOnlyAssertionPassesWhenOnlyOneObjectSaved(): void
    {
        $e1 = new AnyEntity();
        $subject = AnyArrayRepository::withNothing();
        $subject->saveEntity($e1);
        $subject->assertSavedOnly($e1);
    }

    public function testItsSavedOnlyFailsIfNotSaved(): void
    {
        $e1 = new AnyEntity();
        $subject = AnyArrayRepository::with($e1);

        $this->assertAssertionFails(
            function () use ($subject, $e1): void {
                $subject->assertSavedOnly($e1);
            }
        );
    }

    public function testItsSavedOnlyFailsIfDifferentObjectWithSamePropsSaved(): void
    {
        $subject = AnyArrayRepository::with(['prop1' => 'foo'], ['prop1' => 'foo']);
        $e1 = $subject->getEntities()[0];
        $e2 = $subject->getEntities()[1];
        $subject->saveEntity($e2);

        $this->assertAssertionFails(
            function () use ($subject, $e1): void {
                $subject->assertSavedOnly($e1);
            }
        );
    }

    public function testItsSavedOnlyFailsIfObjectModifiedSinceSave(): void
    {
        $e1 = new AnyEntity();
        $subject = AnyArrayRepository::with($e1);
        $subject->saveEntity($e1);

        $e1->setProp2('Bar');

        $this->assertAssertionFails(
            function () use ($subject, $e1): void {
                $subject->assertSavedOnly($e1);
            }
        );
    }
}

/**
 * @extends AbstractArrayRepository<AnyEntity>
 */
class AnyArrayRepository extends AbstractArrayRepository
{
    protected static function getEntityBaseClass(): string
    {
        return AnyEntity::class;
    }

    public function getEntities(): array
    {
        return $this->entities;
    }

    public function assertNothingSaved(): void
    {
        parent::assertNothingSaved();
    }

    public function assertSavedOnly($entity): void
    {
        parent::assertSavedOnly($entity);
    }

    public function countWith($callable): array
    {
        return parent::countWith($callable);
    }

    public function loadWith($callable): object
    {
        return parent::loadWith($callable);
    }

    public function findWith($callable): ?object
    {
        return parent::findWith($callable);
    }

    public function listWith($callable): array
    {
        return parent::listWith($callable);
    }

    public function saveEntity($entity): void
    {
        parent::saveEntity($entity);
    }
}

class AnyEntity
{
    protected $prop1;
    protected $prop2;

    public function getProp1()
    {
        return $this->prop1;
    }

    public function getProp2()
    {
        return $this->prop2;
    }

    public function setProp2($string): void
    {
        $this->prop2 = $string;
    }
}
