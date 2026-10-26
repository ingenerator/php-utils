<?php

namespace test\unit\Ingenerator\PHPUtils\unit\Mutex;

use PDO;
use PDOStatement;
use PHPUnit\Framework\Assert;

class BasicPDOStub extends PDO
{
    /** @var list<array{sql: string, result: array<mixed>}> */
    protected $query_stack = [];

    /** @param list<array{sql: string, result: array<mixed>}> $stack */
    public static function withQueryStack(array $stack): self
    {
        $i = new self();
        $i->query_stack = $stack;

        return $i;
    }

    public function __construct()
    {
    }

    public function quote($string, $type = PDO::PARAM_STR): string|false
    {
        return '{quoted-'.$string.'}';
    }

    public function query(
        string $statement,
        ?int $mode = PDO::ATTR_DEFAULT_FETCH_MODE,
        mixed ...$fetch_mode_args,
    ): PDOStatement|false {
        Assert::assertNotEmpty($this->query_stack, 'No expectation defined for query '.$statement);
        $next_query = array_shift($this->query_stack);
        Assert::assertSame($next_query['sql'], $statement);

        return new BasicPDOStatementStub($next_query['result']);
    }

    public function assertAllQueriesRan(): void
    {
        Assert::assertEquals([], $this->query_stack);
    }
}
