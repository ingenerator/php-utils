<?php

namespace Ingenerator\PHPUtils\Database;

interface TransactionWrapper
{

    /**
     * Run some code in an explicit database transaction
     *
     *   $tx->run(fn() => $this->doComplicatedThings());
     *
     * @param callable $func
     *
     * @return mixed the result of the callable
     */
    public function run(callable $func);

}
