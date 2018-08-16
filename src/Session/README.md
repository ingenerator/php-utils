MySQL Session Handler
=====================

Setup
-----

```
CREATE TABLE `sessions` (
  `id` varchar(40) NOT NULL DEFAULT '',
  `hash` varchar(40) NOT NULL DEFAULT '',
  `session_data` blob NOT NULL,
  `last_active` datetime NOT NULL,
  `session_start` datetime NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `ip` varchar(15) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

Set session expiry using `session.gc_maxlifetime` in `php.ini`

Usage
-----

Call `MysqlSession->initialise();` to set this as your session handler
in your bootstrap.

Garbage collection
------------------

PHP does probability based session GC by default. Using
`session.gc_divisor` and `session.gc_probability` in `php.ini` to
control the frequency

Probability based GC works somewhat but it has few problems.
1) Low traffic sites' session data may not be deleted within the
preferred duration.
2) High traffic sites' GC may be too frequent GC.
3) GC is performed on the user's request and the user will experience a
 GC delay.

Therefore, it is recommended to execute GC periodically for production
systems using cron. Make sure to disable probability based GC by
setting `session.gc_probability = 0`.

As you can only call [session_gc()](http://php.net/manual/en/function.session-gc.php)
after you have called `session_start()` and you may not want / have a
session handler for PHP CLI you can call `garbageCollect()` on
`MySQLSession` to achieve the same effect without initialising a
session first

A sample cron would look something like:
```
$pdo             = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
$session_handler = new Ingenerator\PHPUtils\Session\MysqlSession($pdo, 'insecure-secret');
echo $session_handler->garbageCollect()." sessions were garbage collected".PHP_EOL;
```
