<?php


namespace test\unit\Ingenerator\PHPUtils\unit\Mutex;


use Ingenerator\PHPUtils\Mutex\MockMutexWrapper;
use Ingenerator\PHPUtils\Mutex\MutexTimedOutException;
use PHPUnit\Framework\TestCase;

class MockMutexWrapperTest extends TestCase
{

    public function test_it_calls_callable_and_returns()
    {
        $this->assertSame(
            'callable-result',
            $this->newSubject()->withLock(
                'anything',
                1,
                function () { return 'callable-result'; }
            )
        );
    }

    public function test_it_throws_if_configured_to_throw()
    {
        $subject = $this->newSubject();
        $subject->willTimeoutEverything();
        $this->expectException(MutexTimedOutException::class);
        $subject->withLock(
            'anything',
            1,
            function () { throw new \RuntimeException('Callable should not have run'); }
        );
    }

    protected function newSubject()
    {
        return new MockMutexWrapper;
    }

}
