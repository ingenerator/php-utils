<?php

namespace test\unit\Ingenerator\PHPUtils\Filesystem;


use Ingenerator\PHPUtils\Filesystem\TemporaryDirectoryManager;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class TemporaryDirectoryManagerTest extends TestCase
{

    /**
     * Uses real files rather than vfsStream because of the recursive delete behaviour
     */
    protected string $base_dir;

    public function test_it_is_initialisable(): void
    {
        $this->assertInstanceOf(TemporaryDirectoryManager::class, $this->newSubject());
    }

    public function test_it_creates_temporary_directory_and_returns_path(): void
    {
        $subject = $this->newSubject();
        $path    = $subject->mkTemp('my-dir');
        $path2   = $subject->mkTemp('my-dir');
        $this->assertNotEquals($path, $path2, 'Paths are unique');
        $this->assertMatchesRegularExpression(
            '#^'.\preg_quote($this->base_dir).'/my-dir[a-z0-9\.]+$#',
            $path,
            'Should be in expected path'
        );
        $this->assertDirectoryExists($path, 'Path should exist');
        $this->assertSame(\decoct(0700), \decoct(\fileperms($path) & 0700), 'Path should be private');
    }

    public function test_it_throws_if_directory_cannot_be_created(): void
    {
        $this->markTestSkipped('Skipping due to standalone tests now running as root, this will not fail');
        $this->base_dir = '/';
        $subject        = $this->newSubject();
        $this->expectException(RuntimeException::class);
        $subject->mkTemp('my-dir');
    }

    public function test_it_recursively_removes_temporary_directory_on_explicit_cleanup(): void
    {
        $subject = $this->newSubject();
        $path    = $subject->mkTemp('any-dir');
        \mkdir($path.'/a-child');
        \file_put_contents($path.'/a-child/a-file', 'urk');
        $subject->cleanup($path);
        $this->assertDirectoryDoesNotExist($path, 'Directory should not exist');
        $this->assertDirectoryExists($this->base_dir, 'Parent directory should still exist');
    }

    public function test_it_throws_if_cleanup_a_directory_it_does_not_manage(): void
    {
        $subject = $this->newSubject();
        $this->expectException(InvalidArgumentException::class);
        $subject->cleanup($this->base_dir.'/some-arbitrary-dir');
    }

    public function test_it_removes_remaining_temporary_directories_on_destruction(): void
    {
        $subject = $this->newSubject();
        $paths   = [
            $subject->mkTemp('anything'),
            $subject->mkTemp('otherthing'),
            $subject->mkTemp('anything'),
        ];
        unset($subject);

        $this->assertSame(
            [FALSE, FALSE, FALSE],
            \array_map('is_dir', $paths),
            'Should clean up paths'
        );
    }

    public function test_it_ignores_already_removed_directories_from_cleanup(): void
    {
        $subject = $this->newSubject();
        $path    = $subject->mkTemp('somedir');
        $newpath = \sys_get_temp_dir().'/permanent-path';
        try {
            \rename($path, $newpath);
            $subject->__destruct();
            $this->assertDirectoryExists($newpath, 'Moved dir should still exist');
        } finally {
            \rmdir($newpath);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->base_dir = \sys_get_temp_dir().'/'.\uniqid('temp-dir-mgr');
    }

    protected function newSubject(): TemporaryDirectoryManager
    {
        return new TemporaryDirectoryManager($this->base_dir);
    }
}
