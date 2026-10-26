<?php

namespace test\unit\Ingenerator\PHPUtils\unit\Mutex;

use Ingenerator\PHPUtils\Mutex\DbBackedMutexWrapper;
use Ingenerator\PHPUtils\Mutex\MutexTimedOutException;
use Ingenerator\PHPUtils\Mutex\MutexWrapper;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DbBackedMutexWrapperTest extends TestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $pdo;

    public function testItIsInitialisable()
    {
        $subject = $this->newSubject();
        $this->assertInstanceOf(DbBackedMutexWrapper::class, $subject);
        $this->assertInstanceOf(MutexWrapper::class, $subject);
    }

    public function testItRequestsLockAndThrowsIfNotAvailableWithoutRunningCallback()
    {
        $this->pdo = BasicPDOStub::withQueryStack(
            [
                [
                    'sql' => 'SELECT GET_LOCK({quoted-mylock}, 5)',
                    'result' => [[0]],
                ],
                [
                    'sql' => 'SELECT RELEASE_LOCK({quoted-mylock})',
                    'result' => [[0]],
                ],
            ]
        );

        $subject = $this->newSubject();
        $this->expectException(MutexTimedOutException::class);
        $subject->withLock(
            'mylock',
            5,
            function () { throw new RuntimeException('Callback should never fire'); }
        );
    }

    public function testItReleasesLockAfterSuccessfulCallbackAndReturnsResult()
    {
        $this->pdo = BasicPDOStub::withQueryStack(
            [
                [
                    'sql' => 'SELECT GET_LOCK({quoted-somelock}, 2)',
                    'result' => [[1]],
                ],
                [
                    'sql' => 'SELECT RELEASE_LOCK({quoted-somelock})',
                    'result' => [[0]],
                ],
            ]
        );

        $subject = $this->newSubject();
        $this->assertSame(
            'a value',
            $subject->withLock(
                'somelock',
                2,
                function () { return 'a value'; }
            )
        );
        $this->pdo->assertAllQueriesRan();
    }

    public function testItReleasesLockAfterFailedCallbackAndBubbles()
    {
        $this->pdo = BasicPDOStub::withQueryStack(
            [
                [
                    'sql' => 'SELECT GET_LOCK({quoted-somelock}, 2)',
                    'result' => [[1]],
                ],
                [
                    'sql' => 'SELECT RELEASE_LOCK({quoted-somelock})',
                    'result' => [[0]],
                ],
            ]
        );

        $subject = $this->newSubject();
        try {
            $subject->withLock('somelock', 2, function () { throw new RuntimeException('Stop'); });
            $this->fail('Should have bubbled exception');
        } catch (RuntimeException$e) {
            $this->assertSame('Stop', $e->getMessage());
        }
        $this->pdo->assertAllQueriesRan();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = new BasicPDOStub();
    }

    protected function newSubject()
    {
        return new DbBackedMutexWrapper($this->pdo);
    }
}
