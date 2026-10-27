<?php

declare(strict_types=1);

namespace test\unit\Ingenerator\PHPUtils\Database;

use Ingenerator\PHPUtils\Database\NullTransactionWrapper;
use Ingenerator\PHPUtils\Database\TransactionWrapper;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class NullTransactionWrapperTest extends TestCase
{
    public function testItIsInitialisable(): void
    {
        $subject = $this->newSubject();
        $this->assertInstanceOf(NullTransactionWrapper::class, $subject);
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

    private function newSubject(): NullTransactionWrapper
    {
        return new NullTransactionWrapper();
    }
}
