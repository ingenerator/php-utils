<?php


namespace Ingenerator\PHPUtils\Mutex;


interface MutexWrapper
{
    /**
     * Runs code within a mutex, queuing concurrent invocations until it is free
     *
     * The first call will run to completion and return. Subsequent calls with the same `$name` will
     * block until the mutex is free and then run.
     *
     * - The sequencing of subsequent invocations is undefined and implementation-dependent. Some
     *   may operate a strict FIFO queue, others may run in random sequence.
     *
     * - If the mutex is not free by the timeout, the method will throw.
     *
     * @param string   $name
     * @param int      $timeout_seconds
     * @param callable $exclusive_code
     *
     * @return mixed the result of the provided callable
     * @throws MutexTimedOutException
     */
    public function withLock(string $name, int $timeout_seconds, callable $exclusive_code);
}
