<?php

namespace Ingenerator\PHPUtils\Mutex;

use PDO;
use PDOStatement;
use RuntimeException;

class DbBackedMutexWrapper implements MutexWrapper
{
    public function __construct(protected PDO $db)
    {
    }

    public function withLock(string $name, int $timeout_seconds, callable $exclusive_code)
    {
        try {
            $this->getLock($name, $timeout_seconds);

            return $exclusive_code();
        } finally {
            $this->releaseLock($name);
        }
    }

    protected function getLock(string $name, int $timeout_seconds): void
    {
        // Note: These are borrowed from and could be used for our MysqlSession class
        $name_param = $this->db->quote($name);
        $query = $this->runQuery("SELECT GET_LOCK($name_param, $timeout_seconds)");
        $result = $query->fetchAll(PDO::FETCH_COLUMN, 0);

        // Need to explicitly check as int and as string because the PDO int/string mode varies depending on the
        // runtime configurable PDO attribute.
        $got_lock = match ($result[0]) {
            1, '1' => true,
            default => false,
        };
        if (!$got_lock) {
            throw new MutexTimedOutException($name, $timeout_seconds, $result);
        }
    }

    protected function releaseLock(string $name): void
    {
        $name_param = $this->db->quote($name);
        $query = $this->runQuery("SELECT RELEASE_LOCK($name_param)");
        $query->fetchAll();
    }

    private function runQuery(string $str): PDOStatement
    {
        $query = $this->db->query($str);
        if ($query === false) {
            // If ATTR_ERRMODE was not set to PDO::ERRMODE_EXCEPTION, the method returns false rather than throwing,
            throw new RuntimeException('Failed to execute query'.json_encode($this->db->errorInfo()));
        }

        return $query;
    }
}
