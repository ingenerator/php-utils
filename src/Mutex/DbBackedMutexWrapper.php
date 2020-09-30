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

        if ($result[0] !== '1') {
            throw new MutexTimedOutException($name, $timeout_seconds, $result);
        }
    }

    protected function releaseLock(string $name): void
    {
        $name_param = $this->db->quote($name);
        $this->db->query("SELECT RELEASE_LOCK($name_param)")->fetchAll();
    }

}
