<?php


namespace Ingenerator\PHPUtils\Logging;


use Throwable;

/**
 * Thrown if the logger cannot write to the destination stream
 */
class LoggingFailureException extends \RuntimeException
{

    public function __construct(?string $log_destination, Throwable $failure)
    {
        return parent::__construct(
            sprintf(
                'Could not write to log at `%s` - [%s] %s',
                $log_destination,
                get_class($failure),
                $failure->getMessage()
            ),
            0,
            $failure
        );
    }
}
