<?php

namespace test\unit\Ingenerator\PHPUtils\unit\Mutex;

use PDO;
use function array_map;
use const PHP_MAJOR_VERSION;

if (PHP_MAJOR_VERSION < 8) {
    class BasicPDOStatementStub extends \PDOStatement
    {
        protected $result;

        public function __construct(array $result) { $this->result = $result; }

        public function fetchAll($fetch_style = NULL, $fetch_argument = NULL, $ctor_args = NULL): array
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
} else {
    class BasicPDOStatementStub extends \PDOStatement
    {
        protected $result;

        public function __construct(array $result) { $this->result = $result; }

        public function fetchAll(int $fetch_style = NULL, mixed ...$fetch_argument): array
        {
            if (PHP_VERSION_ID < 80100) {
                // Before 8.1, ints and floats were returned as strings
                // https://www.php.net/manual/en/migration81.incompatible.php#migration81.incompatible.pdo.mysql
                $this->result = array_map(
                    fn($row) => array_map(
                        fn($column) => (is_int($column) || \is_float($column)) ? (string) $column : $column,
                        $row
                    ),
                    $this->result
                );
            }

            if ($fetch_style === NULL) {
                return $this->result;
            } elseif ($fetch_style === PDO::FETCH_COLUMN) {
                return array_map(fn($row) => $row[$fetch_argument[0]], $this->result);
            } else {
                throw new \UnexpectedValueException('Not mocked '.$fetch_style);
            }
        }


    }
}
