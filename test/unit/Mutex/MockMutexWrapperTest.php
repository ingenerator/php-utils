<?php

namespace test\unit\Ingenerator\PHPUtils\unit\Mutex;

use Ingenerator\PHPUtils\Mutex\MockMutexWrapper;
use Ingenerator\PHPUtils\Mutex\MutexTimedOutException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class MockMutexWrapperTest extends TestCase
{
    public function testItCallsCallableAndReturns()
    {
        $this->assertSame(
            'callable-result',
            $this->newSubject()->withLock(
                'anything',
                1,
                fn () => 'callable-result'
            )
        );
    }

    public function testItThrowsIfConfiguredToThrow()
    {
        $subject = $this->newSubject();
        $subject->willTimeoutEverything();
        $this->expectException(MutexTimedOutException::class);
        $subject->withLock(
            'anything',
            1,
            function () { throw new RuntimeException('Callable should not have run'); }
        );
    }

    protected function newSubject()
    {
        return new MockMutexWrapper();
    }
}
