<?php


namespace Ingenerator\PHPUtils\Mutex;


use PDO;

class DbBackedMutexWrapper implements MutexWrapper
{
    /**
     * @var PDO
     */
    protected $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
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
        $result     = $this->db
            ->query("SELECT GET_LOCK($name_param, $timeout_seconds)")
            ->fetchAll(PDO::FETCH_COLUMN, 0);

        // Need to explicitly cast the result to an int for comparison as PDO value types vary between 8.0 and 8.1+
        // And we should keep this cast even when we drop 8.0, because the PDO int/string mode is actually
        // configurable with a PDO attribute so could vary at runtime too.
        if (1 !== (int) $result[0]) {
            throw new MutexTimedOutException($name, $timeout_seconds, $result);
        }
    }

    protected function releaseLock(string $name): void
    {
        $name_param = $this->db->quote($name);
        $this->db->query("SELECT RELEASE_LOCK($name_param)")->fetchAll();
    }

}
