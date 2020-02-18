<?php
/**
 * @author    Craig Gosman <craig@ingenerator.com>
 * @licence   proprietary
 */


namespace Ingenerator\PHPUtils\Session;


use Ingenerator\PHPUtils\DateTime\DateString;
use PDO;
use SessionHandlerInterface;

class MysqlSession implements SessionHandlerInterface, \SessionUpdateTimestampHandlerInterface, \SessionIdInterface
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
     * @var string[]
     */
    protected $data_cache;

    /**
     * @var string
     */
    protected $client_ip;

    /**
     * @var string
     */
    protected $client_user_agent;

    /**
     * @param PDO    $db
     * @param string $hash_salt
     * @param int    $lock_timeout seconds to wait for MySQL lock
     */
    public function __construct(PDO $db, $hash_salt, $lock_timeout = 20)
    {
        $this->db                = $db;
        $this->hash_salt         = $hash_salt;
        $this->lock_timeout      = $lock_timeout;
        $this->session_lifetime  = \ini_get('session.gc_maxlifetime');
        $this->client_ip         = $_SERVER['REMOTE_ADDR'] ?? '';
        $this->client_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    /**
     * @return void
     */
    public function initialise()
    {
        // strict mode is required both for general security, and to enable the create_sid and
        // validateId methods in this handler interface, which won't otherwise be called.
        \ini_set('session.use_strict_mode', TRUE);
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
    public function destroy($session_id)
    {
        return $this->db->prepare("DELETE FROM `sessions` WHERE `id` = :id")
            ->execute(['id' => $session_id]);
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
        $gc->execute(
            ['expire' => DateString::ymdhis($now->sub(new \DateInterval('PT'.$maxlifetime.'S')))]
        );

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
     * Called any time a new session ID needs to be created, because the user has none or they have expired
     *
     * {@inheritDoc}
     */
    public function create_sid()
    {
        $session_id = \session_create_id();

        /*
         * The session does not exist, so create it in the DB
         *
         * We do not need to take a lock for this, as no other process can be issuing the same ID.
         * Anything that gets written into session during this request will be written to the DB
         * before we send the session cookie back to the user, so the lock is not necessary.
         *
         * This also means that bots, crawlers etc will still involve creating a new session in the
         * db but will not need to take or release the lock, marginally reducing db impact of these
         * operations.
         *
         * Inserting at time of creation is the most efficient at the DB, and also means that
         * if a user with multiple dormant tabs opens them all at once, each concurrent request
         * will be sent a new session cookie. This avoids any conflict if any of those processes
         * are sitting in a transaction at the same time as each other. The client will ultimately
         * be given multiple new empty sessions (so the original requests will all work / show they
         * are logged out). Their browser will then pick the session ID from whichever completed
         * last and use that going forward. The others will immediately be orphaned and will be
         * garbage collected in due course.
         */

        $this->db->prepare(
            "INSERT INTO `sessions` (`id`, `hash`, `session_data`, `last_active`, `session_start`, `user_agent`, `ip`)
                          VALUES (:id, :hash, :data, :now, :now, :user_agent, :ip)"
        )->execute(
            [
                'id'         => $session_id,
                'hash'       => $this->calculateHash(),
                'data'       => '',
                'now'        => \date('Y-m-d H:i:s'),
                'user_agent' => $this->client_user_agent,
                'ip'         => $this->client_ip,
            ]
        );

        // Cache the data for the read call which will immediately follow
        $this->data_cache[$session_id] = '';

        return $session_id;
    }


    /**
     * {@inheritDoc}
     */
    public function validateId($session_id)
    {
        $now = new \DateTimeImmutable();
        $this->getLock($session_id);

        $query = $this->db->prepare(
            "SELECT `session_data` FROM `sessions` WHERE `id` = :id AND `last_active` > :expire AND `hash` = :hash LIMIT 1"
        );
        $query->execute(
            [
                'id'     => $session_id,
                'expire' => DateString::ymdhis(
                    $now->sub(new \DateInterval('PT'.$this->session_lifetime.'S'))
                ),
                'hash'   => $this->calculateHash(),
            ]
        );

        // it is recommended to use fetchAll so that PDO can close the DB cursor, even tho we only expect one row
        $rows = $query->fetchAll(PDO::FETCH_ASSOC);

        if ($session_data = ($rows[0]['session_data'] ?? NULL)) {
            // OK, they have a session, not expired and the hash is correct
            // Cache the data for the read call that will immediately follow
            // And keep the lock as we need to hold it till the end of their request
            $this->data_cache[$session_id] = $session_data;

            return TRUE;

        } else {
            // No data means:
            // - the session has expired
            // - this request is a session fixation attack
            // - the hash salt or algorithm has changed (e.g. a code change)
            //
            // In any of these cases, we want to issue a new session ID to this client
            // We also want to release the lock on the current ID so that a session fixation can't
            // lock someone else's session.
            //
            // Returning false here prompts php to call ->create_sid() and issue a new session ID
            $this->releaseLock();

            return FALSE;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function read($session_id)
    {
        if ( ! isset($this->data_cache[$session_id])) {
            // This cannot realistically ever happen in real life. PHP will *always* have called
            // either create_sid() or validateId() before it calls read, and both those methods
            // cache a value for the session.
            throw new \BadMethodCallException(
                'Session data for '.$session_id.' has not been cached - session ID not validated?'
            );
        }

        // Note we leave data_cache set, so will have a second copy of the session data in memory
        // for the duration of the request. However that avoids any issues from an unexpected second
        // call to read (unlikely but not impossible) not being able to get the data.
        return $this->data_cache[$session_id];
    }

    /**
     * Called to write the session data when anything in $_SESSION has changed
     *
     *
     * If $_SESSION is unchanged, updateTimestamp() will be called instead (so long as
     * session.lazy_write is set to the default `1` in php config)
     *
     * {@inheritdoc}
     */
    public function write($session_id, $session_data)
    {
        // There is no reason to update user_agent or hash
        // If user_agent has changed the hash will have changed
        // If the hash has changed the user will have been issued a new session ID after validateId
        return $this->db->prepare(
            "UPDATE sessions
                        SET `session_data` = :data,
                            `last_active`  = :now,
                            `ip`           = :ip
                        WHERE id = :id"
        )->execute(
            [
                'id'   => $session_id,
                'data' => $session_data,
                'now'  => \date('Y-m-d H:i:s'),
                'ip'   => $this->client_ip,
            ]
        );
    }

    /**
     * Called to mark a session still active even if the data hasn't changed
     */
    public function updateTimestamp($session_id, $session_data)
    {
        // There is no reason to update user_agent or hash
        // If user_agent has changed the hash will have changed
        // If the hash has changed the user will have been issued a new session ID after validateId

        return $this->db->prepare(
            "UPDATE sessions
                        SET `last_active`  = :now,
                            `ip`           = :ip
                        WHERE id = :id"
        )->execute(
            [
                'id'  => $session_id,
                'now' => \date('Y-m-d H:i:s'),
                'ip'  => $this->client_ip,
            ]
        );
    }

    /**
     * @return string
     */
    protected function calculateHash()
    {
        $hash = $this->client_user_agent.$this->hash_salt;

        return \sha1($hash);
    }

    /**
     * @param $session_id
     *
     * @return bool
     * @throws \ErrorException
     */
    protected function getLock($session_id)
    {
        $this->session_lock = 'session_'.$session_id;

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
        if ( ! $this->session_lock) {
            // The lock has already been released e.g. due to validateSid releasing it because
            // we did not have a valid claim on that ID (expired / fixation)
            return TRUE;
        }

        $query = $this->db->prepare("SELECT RELEASE_LOCK(:session_lock)");
        $query->execute(['session_lock' => $this->session_lock]);
        $result = $query->fetchColumn(0);

        if ($result == 1) {
            // Mark the session lock already cleared
            $this->session_lock = NULL;

            return TRUE;
        }

        return FALSE;
    }
}
