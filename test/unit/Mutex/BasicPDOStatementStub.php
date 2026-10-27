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
            $column = $fetch_argument[0] ?? 0;
            if (!is_int($column) && !is_string($column)) {
                throw new UnexpectedValueException('Column index must be int or string');
            }

            return array_map(
                static function (mixed $row) use ($column): mixed {
                    if (!is_array($row)) {
                        throw new UnexpectedValueException('Expected array row');
                    }

                    return $row[$column] ?? null;
                },
                $this->result
            );
        }
        throw new UnexpectedValueException('Not mocked '.$mode);
    }
}
