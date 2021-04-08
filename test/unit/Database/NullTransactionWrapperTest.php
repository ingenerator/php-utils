<?php


namespace test\unit\Ingenerator\PHPUtils\Database;


use Ingenerator\PHPUtils\Database\NullTransactionWrapper;
use Ingenerator\PHPUtils\Database\TransactionWrapper;
use PHPUnit\Framework\TestCase;

class NullTransactionWrapperTest extends TestCase
{

    public function test_it_is_initialisable()
    {
        $subject = $this->newSubject();
        $this->assertInstanceOf(NullTransactionWrapper::class, $subject);
        $this->assertInstanceOf(TransactionWrapper::class, $subject);
    }

    public function test_it_runs_code_and_returns_result()
    {
        $this->assertSame(
            [
                'ran'  => TRUE,
                'args' => [],
            ],
            $this->newSubject()->run(fn(...$args) => ['ran' => TRUE, 'args' => $args])
        );
    }

    public function test_it_bubbles_exceptions()
    {
        $subject = $this->newSubject();
        $this->expectExceptionMessage('I broke');
        $subject->run(function () { throw new \RuntimeException('I broke'); });
    }

    private function newSubject(): NullTransactionWrapper
    {
        return new NullTransactionWrapper;
    }

}
