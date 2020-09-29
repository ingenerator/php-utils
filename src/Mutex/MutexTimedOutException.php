<?php


namespace Ingenerator\PHPUtils\Mutex;

use Ingenerator\PHPUtils\StringEncoding\JSON;

class MutexTimedOutException extends \RuntimeException
{
    public function __construct(string $name, string $timeout, $result)
    {
        parent::__construct(
            sprintf(
                'Could not obtain lock `%s`: timeout %s - result %s',
                $name,
                $timeout,
                JSON::encode($result)
            )
        );
    }
}
