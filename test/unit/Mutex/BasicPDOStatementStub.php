<?php

namespace test\unit\Ingenerator\PHPUtils\unit\Mutex;

use PDO;
use const PHP_MAJOR_VERSION;

if (PHP_MAJOR_VERSION < 8) {
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
} else {
    class BasicPDOStatementStub extends \PDOStatement
    {
        protected $result;

        public function __construct(array $result) { $this->result = $result; }

        public function fetchAll(int $fetch_style = NULL, mixed ...$fetch_argument)
        {
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