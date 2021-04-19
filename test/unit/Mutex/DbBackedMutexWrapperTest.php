<?php


namespace test\unit\Ingenerator\PHPUtils\unit\Mutex;


use Ingenerator\PHPUtils\Mutex\DbBackedMutexWrapper;
use Ingenerator\PHPUtils\Mutex\MutexTimedOutException;
use Ingenerator\PHPUtils\Mutex\MutexWrapper;
use PHPUnit\Framework\TestCase;

class DbBackedMutexWrapperTest extends TestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $pdo;

    public function test_it_is_initialisable()
    {
        $subject = $this->newSubject();
        $this->assertInstanceOf(DbBackedMutexWrapper::class, $subject);
        $this->assertInstanceOf(MutexWrapper::class, $subject);
    }

    public function test_it_requests_lock_and_throws_if_not_available_without_running_callback()
    {
        $this->pdo = BasicPDOStub::withQueryStack(
            [
                [
                    'sql'    => 'SELECT GET_LOCK({quoted-mylock}, 5)',
                    'result' => [['0']]
                ],
                [
                    'sql'    => 'SELECT RELEASE_LOCK({quoted-mylock})',
                    'result' => [['0']]
                ],
            ]
        );

        $subject = $this->newSubject();
        $this->expectException(MutexTimedOutException::class);
        $subject->withLock(
            'mylock',
            5,
            function () { throw new \RuntimeException('Callback should never fire'); }
        );
    }

    public function test_it_releases_lock_after_successful_callback_and_returns_result()
    {
        $this->pdo = BasicPDOStub::withQueryStack(
            [
                [
                    'sql'    => 'SELECT GET_LOCK({quoted-somelock}, 2)',
                    'result' => [['1']]
                ],
                [
                    'sql'    => 'SELECT RELEASE_LOCK({quoted-somelock})',
                    'result' => [['0']]
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

    public function test_it_releases_lock_after_failed_callback_and_bubbles()
    {
        $this->pdo = BasicPDOStub::withQueryStack(
            [
                [
                    'sql'    => 'SELECT GET_LOCK({quoted-somelock}, 2)',
                    'result' => [['1']]
                ],
                [
                    'sql'    => 'SELECT RELEASE_LOCK({quoted-somelock})',
                    'result' => [['0']]
                ],
            ]
        );

        $subject = $this->newSubject();
        try {
            $subject->withLock('somelock', 2, function () { throw new \RuntimeException('Stop'); });
            $this->fail('Should have bubbled exception');
        } catch (\RuntimeException$e) {
            $this->assertSame('Stop', $e->getMessage());
        }
        $this->pdo->assertAllQueriesRan();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = new BasicPDOStub;
    }

    protected function newSubject()
    {
        return new DbBackedMutexWrapper($this->pdo);
    }

}
