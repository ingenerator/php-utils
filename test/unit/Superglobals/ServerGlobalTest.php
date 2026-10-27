<?php

declare(strict_types=1);

namespace test\unit\Ingenerator\PHPUtils\Superglobals;

use Ingenerator\PHPUtils\Superglobals\ServerGlobal;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class ServerGlobalTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $old_server;

    protected function setUp(): void
    {
        $this->old_server = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->old_server;
    }

    #[TestWith(['REMOTE_ADDR', '127.0.0.1'])]
    #[TestWith(['HTTP_USER_AGENT', ''])]
    public function testItReturnsStringValuesFromFake(string $key, string $expect): void
    {
        $subject = ServerGlobal::fake([
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_USER_AGENT' => '',
        ]);

        $this->assertSame($expect, $subject->getString($key));
    }

    public function testItReturnsNullForAMissingFakeValue(): void
    {
        $subject = ServerGlobal::fake(['REMOTE_ADDR' => '127.0.0.1']);

        $this->assertNull($subject->getString('HTTP_HOST'));
    }

    public function testFakeDoesNotFallBackToServerSuperglobal(): void
    {
        $_SERVER = ['REMOTE_ADDR' => 'global value'];
        $subject = ServerGlobal::fake([]);

        $this->assertNull($subject->getString('REMOTE_ADDR'));
    }

    public function testItReadsFromServerSuperglobalByDefault(): void
    {
        $_SERVER = ['REMOTE_ADDR' => '127.0.0.1'];
        $subject = new ServerGlobal();

        $this->assertSame('127.0.0.1', $subject->getString('REMOTE_ADDR'));
        $this->assertNull($subject->getString('HTTP_HOST'));
    }

    public function testByDefaultReadsLiveSuperglobal(): void
    {
        // Prove that the instance doesn't take a fixed copy of $_SERVER, but instead reads from the live global array.
        $_SERVER = ['HTTP_HOST' => 'foo'];
        $subject = new ServerGlobal();

        $this->assertSame('foo', $subject->getString('HTTP_HOST'));
        $_SERVER['HTTP_HOST'] = 'overwritten';
        $this->assertSame('overwritten', $subject->getString('HTTP_HOST'));
    }
}
