<?php

declare(strict_types=1);

namespace test\unit\Ingenerator\PHPUtils\unit\Mutex;

use Ingenerator\PHPUtils\Mutex\MockMutexWrapper;
use Ingenerator\PHPUtils\Mutex\MutexTimedOutException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MockMutexWrapperTest extends TestCase
{
    public function testItCallsCallableAndReturns(): void
    {
        $this->assertSame(
            'callable-result',
            $this->newSubject()->withLock(
                'anything',
                1,
                fn (): string => 'callable-result'
            )
        );
    }

    public function testItThrowsIfConfiguredToThrow(): void
    {
        $subject = $this->newSubject();
        $subject->willTimeoutEverything();
        $this->expectException(MutexTimedOutException::class);
        $subject->withLock(
            'anything',
            1,
            function (): never { throw new RuntimeException('Callable should not have run'); }
        );
    }

    protected function newSubject(): MockMutexWrapper
    {
        return new MockMutexWrapper();
    }
}
