<?php

namespace test\unit\Ingenerator\PHPUtils\Database;

use Ingenerator\PHPUtils\Database\NullTransactionWrapper;
use Ingenerator\PHPUtils\Database\TransactionWrapper;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class NullTransactionWrapperTest extends TestCase
{
    public function testItIsInitialisable()
    {
        $subject = $this->newSubject();
        $this->assertInstanceOf(NullTransactionWrapper::class, $subject);
        $this->assertInstanceOf(TransactionWrapper::class, $subject);
    }

    public function testItRunsCodeAndReturnsResult()
    {
        $this->assertSame(
            [
                'ran' => true,
                'args' => [],
            ],
            $this->newSubject()->run(fn (...$args) => ['ran' => true, 'args' => $args])
        );
    }

    public function testItBubblesExceptions()
    {
        $subject = $this->newSubject();
        $this->expectExceptionMessage('I broke');
        $subject->run(function () { throw new RuntimeException('I broke'); });
    }

    private function newSubject(): NullTransactionWrapper
    {
        return new NullTransactionWrapper();
    }
}
