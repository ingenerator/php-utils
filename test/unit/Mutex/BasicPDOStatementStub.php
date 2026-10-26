<?php

namespace test\unit\Ingenerator\PHPUtils\unit\Mutex;

use PDO;
use PDOStatement;
use UnexpectedValueException;

use function array_map;

class BasicPDOStatementStub extends PDOStatement
{
    /** @var array<mixed> */
    protected $result;

    /** @param array<mixed> $result */
    public function __construct(array $result)
    {
        $this->result = $result;
    }

    /** @return array<mixed> */
    public function fetchAll(?int $mode = null, mixed ...$fetch_argument): array
    {
        if ($mode === null) {
            return $this->result;
        }
        if ($mode === PDO::FETCH_COLUMN) {
            return array_map(fn (array $row) => $row[$fetch_argument[0]], $this->result);
        }
        throw new UnexpectedValueException('Not mocked '.$mode);
    }
}
