<?php


namespace Ingenerator\PHPUtils\Database;


class NullTransactionWrapper implements TransactionWrapper
{
    public function run(callable $func)
    {
        return $func();
    }

}
