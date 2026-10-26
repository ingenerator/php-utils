<?php

namespace Ingenerator\PHPUtils\Mutex;

use LogicException;

/**
 * @phpstan-type  TLockHistoryEntry array{name: string, timeout: int}
 */
class MockMutexWrapper implements MutexWrapper
{
    protected bool $always_timeout = false;

    protected ?string $current_lock_name = null;

    /**
     * @var list<TLockHistoryEntry>
     */
    protected array $lock_history = [];

    public function withLock(string $name, int $timeout_seconds, callable $exclusive_code)
    {
        $this->lock_history[] = ['name' => $name, 'timeout' => $timeout_seconds];

        if ($this->always_timeout) {
            throw new MutexTimedOutException($name, $timeout_seconds, 'forced');
        }

        $this->current_lock_name = $name;
        try {
            return $exclusive_code();
        } finally {
            $this->current_lock_name = null;
        }
    }

    /**
     * Use this *inside* the locked section from a test to verify the use of the mutex.
     *
     * A test like:
     *
     *   $result = $mutex->withLock(
     *     'anything',
     *     1,
     *     function () use ($mutex) { return $mutex->getCurrentLockName(); }
     *   );
     *   assert('anything' === $result);
     *
     * Will verify that a) the function was correctly run *inside* the mutex and b) it was locked with
     * the expected name. This is obviously more useful when testing through code that can itself take
     * a callback or `next` function (e.g. a middleware).
     */
    public function getCurrentLockName(): string
    {
        if ($this->current_lock_name === null) {
            throw new LogicException('There is no current mutex lock');
        }

        return $this->current_lock_name;
    }

    /**
     * Use to verify the locks that were taken (name / timeout) in sequence.
     *
     * This is useful where you can only observe the outside of your process, but does not provide a way
     * to verify that the application code actually ran *inside* the mutex rather than before / after it.
     * Where possible use an injected callback and the ->getCurrentLockName method.
     *
     * @return list<TLockHistoryEntry>
     */
    public function getLockHistory(): array
    {
        return $this->lock_history;
    }

    public function willTimeoutEverything(): void
    {
        $this->always_timeout = true;
    }
}
