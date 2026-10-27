<?php

declare(strict_types=1);

namespace Ingenerator\PHPUtils\Mutex;

use Ingenerator\PHPUtils\StringEncoding\JSON;
use RuntimeException;

class MutexTimedOutException extends RuntimeException
{
    public function __construct(string $name, int $timeout, mixed $result)
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
