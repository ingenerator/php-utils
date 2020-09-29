<?php


namespace Ingenerator\PHPUtils\Mutex;


class MockMutexWrapper implements MutexWrapper
{
    protected $always_timeout = FALSE;

    public function withLock(string $name, int $timeout_seconds, callable $exclusive_code)
    {
        if ($this->always_timeout) {
            throw new MutexTimedOutException($name, $timeout_seconds, 'forced');
        }

        return $exclusive_code();
    }

    public function willTimeoutEverything(): void
    {
        $this->always_timeout = TRUE;
    }

}
