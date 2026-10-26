<?php

namespace test\unit\Ingenerator\PHPUtils\unit\Mutex;

use PDO;
use PDOStatement;
use UnexpectedValueException;

use function array_map;

use const PHP_MAJOR_VERSION;

if (PHP_MAJOR_VERSION < 8) {
    class BasicPDOStatementStub extends PDOStatement
    {
        protected $result;

        public function __construct(array $result)
        {
            $this->result = $result;
        }

        public function fetchAll($fetch_style = null, $fetch_argument = null, $ctor_args = null): array
        {
            if ($fetch_style === null) {
                return $this->result;
            }
            if ($fetch_style === PDO::FETCH_COLUMN) {
                return array_map(
                    fn (array $row) => $row[$fetch_argument],
                    $this->result
                );
            }
            throw new UnexpectedValueException('Not mocked '.$fetch_style);
        }
    }
} else {
    class BasicPDOStatementStub extends PDOStatement
    {
        protected $result;

        public function __construct(array $result)
        {
            $this->result = $result;
        }

        public function fetchAll(?int $fetch_style = null, mixed ...$fetch_argument): array
        {
            if ($fetch_style === null) {
                return $this->result;
            }
            if ($fetch_style === PDO::FETCH_COLUMN) {
                return array_map(fn (array $row) => $row[$fetch_argument[0]], $this->result);
            }
            throw new UnexpectedValueException('Not mocked '.$fetch_style);
        }
    }
}
