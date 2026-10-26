<?php

declare(strict_types=1);

namespace test\unit\Ingenerator\PHPUtils\Database;

use Doctrine\ORM\EntityManagerInterface;
use Ingenerator\PHPUtils\Database\Doctrine2TransactionWrapper;
use Ingenerator\PHPUtils\Database\TransactionWrapper;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function class_alias;

final class Doctrine2TransactionWrapperTest extends TestCase
{
    private EntityManagerInterface $entity_manager_mock;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        class_alias(StubEntityManager::class, EntityManagerInterface::class);
    }

    public function testItIsInitialisable(): void
    {
        $subject = $this->newSubject();
        $this->assertInstanceOf(Doctrine2TransactionWrapper::class, $subject);
        $this->assertInstanceOf(TransactionWrapper::class, $subject);
    }

    public function testItRunsCodeAndReturnsResult(): void
    {
        $this->assertSame(
            [
                'ran' => true,
                'args' => [],
            ],
            $this->newSubject()->run(fn (...$args): array => ['ran' => true, 'args' => $args])
        );
    }

    public function testItBubblesExceptions(): void
    {
        $subject = $this->newSubject();
        $this->expectExceptionMessage('I broke');
        $subject->run(function (): never { throw new RuntimeException('I broke'); });
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->entity_manager_mock = new StubEntityManager();
    }

    private function newSubject(): Doctrine2TransactionWrapper
    {
        return new Doctrine2TransactionWrapper($this->entity_manager_mock);
    }
}

/**
 * Implement the bare-minimum part of the EntityManagerInterface to allow us to test the caller without a dependency
 * on Doctrine2.
 */
class StubEntityManager
{
    public function transactional($func)
    {
        return $func();
    }
}
