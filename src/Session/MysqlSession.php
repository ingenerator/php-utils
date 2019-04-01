<?php
/**
 * @author    Craig Gosman <craig@ingenerator.com>
 * @licence   proprietary
 */


namespace Ingenerator\PHPUtils\Session;


use PDO;
use SessionHandlerInterface;

class MysqlSession implements SessionHandlerInterface
{
    /**
     * @var PDO
     */
    protected $db;

    /**
     * @var string
     */
    protected $hash_salt;

    /**
     * @var int
     */
    protected $lock_timeout;

    /**
     * @var int
     */
    protected $session_lifetime;

    /**
     * @var string
     */
    protected $session_lock;

    /**
     * @param PDO    $db
     * @param string $hash_salt
     * @param int    $lock_timeout seconds to wait for MySQL lock
     */
    public function __construct(PDO $db, $hash_salt, $lock_timeout = 20)
    {
        $this->db               = $db;
        $this->hash_salt        = $hash_salt;
        $this->lock_timeout     = $lock_timeout;
        $this->session_lifetime = \ini_get('session.gc_maxlifetime');
    }

    /**
     * @return void
     */
    public function initialise()
    {
        \session_set_save_handler($this, TRUE);
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        return $this->releaseLock();
    }

    /**
     * {@inheritdoc}
     */
    public function destroy($id)
    {
        return $this->db->prepare("DELETE FROM `sessions` WHERE `id` = :id")
            ->execute(['id' => $id]);
    }

    /**
     * Garbage collects expired sessions based on current session.gc_maxlifetime
     *
     * @return int
     */
    public function garbageCollect()
    {
        return $this->gc($this->session_lifetime);
    }

    /**
     * {@inheritdoc}
     */
    public function gc($maxlifetime)
    {
        $now = new \DateTimeImmutable();
        $gc  = $this->db->prepare("DELETE FROM `sessions` WHERE `last_active` < :expire");
        $gc->execute(['expire' => $now->sub(new \DateInterval('PT'.$maxlifetime.'S'))->format('Y-m-d H:i:s')]);

        return $gc->rowCount();
    }

    /**
     * {@inheritdoc}
     */
    public function open($save_path, $name)
    {
        return TRUE;
    }

    /**
     * {@inheritdoc}
     */
    public function read($id)
    {
        $now = new \DateTimeImmutable();

        $this->getLock($id);

        $query = $this->db->prepare(
            "SELECT `session_data` FROM `sessions` WHERE `id` = :id AND `last_active` > :expire AND `hash` = :hash LIMIT 1"
        );
        $query->execute(
            [
                'id'     => $id,
                'expire' => $now->sub(new \DateInterval('PT'.$this->session_lifetime.'S'))->format('Y-m-d H:i:s'),
                'hash'   => $this->calculateHash(),
            ]
        );

        if ($result = $query->fetchColumn(0)) {
            return $result;
        }

        // on error return an empty string - this HAS to be an empty string
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function write($id, $session_data)
    {
        $user_agent = '';
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $user_agent = $_SERVER['HTTP_USER_AGENT'];
        }

        $ip = '';
        if (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return $this->db->prepare(
            "INSERT INTO `sessions` (`id`, `hash`, `session_data`, `last_active`, `session_start`, `user_agent`, `ip`) 
                          VALUES (:id, :hash, :data, :now, :now, :user_agent, :ip) 
                          ON DUPLICATE KEY UPDATE session_data = :data, last_active = :now, user_agent = :user_agent, ip = :ip"
        )->execute(
            [
                'id'         => $id,
                'hash'       => $this->calculateHash(),
                'data'       => $session_data,
                'now'        => \date('Y-m-d H:i:s'),
                'user_agent' => $user_agent,
                'ip'         => $ip,
            ]
        );
    }

    /**
     * @return string
     */
    protected function calculateHash()
    {
        $hash = '';

        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $hash .= $_SERVER['HTTP_USER_AGENT'];
        }

        $hash .= $this->hash_salt;

        return \sha1($hash);
    }

    /**
     * @param $id
     *
     * @return bool
     * @throws \ErrorException
     */
    protected function getLock($id)
    {
        $this->session_lock = 'session_'.$id;

        $query = $this->db->prepare("SELECT GET_LOCK(:session_lock, :timeout)");
        $query->execute(['session_lock' => $this->session_lock, 'timeout' => $this->lock_timeout]);
        $result = $query->fetchColumn(0);

        if ($result != 1) {
            throw new SessionLockNotObtainedException('Could not obtain session lock!');
        }

        return TRUE;
    }

    /**
     * @return bool
     */
    protected function releaseLock()
    {
        $query = $this->db->prepare("SELECT RELEASE_LOCK(:session_lock)");
        $query->execute(['session_lock' => $this->session_lock]);
        $result = $query->fetchColumn(0);

        if ($result == 1) {
            return TRUE;
        }

        return FALSE;
    }
}
