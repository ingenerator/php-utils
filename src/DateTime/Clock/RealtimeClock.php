<?php
/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 * @licence   proprietary
 */

namespace Ingenerator\PHPUtils\DateTime\Clock;

/**
 * Simple wrapper around current date/time methods to allow easy injection of fake time in
 * dependent classes
 *
 * @package Ingenerator\Util
 */
class RealtimeClock
{

    /**
     * @return \DateTimeImmutable
     */
    public function getDateTime()
    {
        return new \DateTimeImmutable;
    }

    /**
     * @return float
     */
    public function getMicrotime()
    {
        return \microtime(TRUE);
    }

    /**
     * @param $microseconds
     */
    public function usleep($microseconds)
    {
        \usleep($microseconds);
    }
}
