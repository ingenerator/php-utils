<?php
/**
 * @author    Craig Gosman <craig@ingenerator.com>
 * @licence   proprietary
 */

namespace test\unit\Ingenerator\PHPUtils\Session;


use Ingenerator\PHPUtils\Session\MysqlSession;
use PHPUnit\Framework\TestCase;

class MysqlSessionTest extends TestCase
{

    public function test_it_is_initialisable()
    {
        $this->assertInstanceOf(MysqlSession::class, $this->newSubject());
    }

    protected function newSubject()
    {
        return new MysqlSession(new PDOMock, 'insecure-salt');
    }

}

class PDOMock extends \PDO {
    public function __construct() {}
}
