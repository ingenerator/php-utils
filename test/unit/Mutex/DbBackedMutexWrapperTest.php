<?php


namespace test\unit\Ingenerator\PHPUtils\unit\Mutex;


use Ingenerator\PHPUtils\Mutex\DbBackedMutexWrapper;
use Ingenerator\PHPUtils\Mutex\MutexTimedOutException;
use Ingenerator\PHPUtils\Mutex\MutexWrapper;
use PDO;
use PHPUnit\Framework\Assert;
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

class BasicPDOStub extends PDO
{
    protected $query_stack = [];

    public static function withQueryStack(array $stack)
    {
        $i              = new static;
        $i->query_stack = $stack;

        return $i;
    }

    public function __construct() { }

    public function quote($string, $parameter_type = PDO::PARAM_STR)
    {
        return '{quoted-'.$string.'}';
    }

    public function query(
        $statement,
        $mode = PDO::ATTR_DEFAULT_FETCH_MODE,
        $arg3 = NULL,
        array $ctorargs = []
    ) {
        Assert::assertNotEmpty($this->query_stack, 'No expectation defined for query '.$statement);
        $next_query = array_shift($this->query_stack);
        Assert::assertSame($next_query['sql'], $statement);

        return new BasicPDOStatementStub($next_query['result']);
    }

    public function assertAllQueriesRan()
    {
        Assert::assertEquals([], $this->query_stack);
    }
}

class BasicPDOStatementStub extends \PDOStatement
{
    protected $result;

    public function __construct(array $result) { $this->result = $result; }

    public function fetchAll($fetch_style = NULL, $fetch_argument = NULL, $ctor_args = NULL)
    {
        if ($fetch_style === NULL) {
            return $this->result;
        } elseif ($fetch_style === PDO::FETCH_COLUMN) {
            return array_map(
                function ($row) use ($fetch_argument) {
                    return $row[$fetch_argument];
                },
                $this->result
            );
        } else {
            throw new \UnexpectedValueException('Not mocked '.$fetch_style);
        }
    }


}
