<?php


namespace Ingenerator\PHPUtils\Logging;


/**
 * Implement LogMetadataProvider to lazily provide metadata whenever the first log entry is written
 *
 * It is **IMPERATIVE** that implementations of this class cannot throw exceptions during the constructor, even by
 * reference to external classes or global state. If the class cannot be constructed your entire logging framework is
 * broken, and your application cannot log that fact...
 *
 * @package Ingenerator\PHPUtils\Logging
 */
interface LogMetadataProvider
{

    /**
     * Return an array of metadata to be merged into the metadata from other sources
     *
     * Any errors/exceptions thrown during this method will be caught and sent as an additional log entry. You should
     * catch and handle any expected exceptions (e.g. logs being fired before the application is fully bootstrapped) and
     * if appropriate return a warning value in your array. Only unexpected / system-level exceptions (failure to
     * connect to external service / invalid configuration even when fully bootstrapped / etc) should bubble to allow
     * these to be detected and fixed.
     *
     * @return array
     */
    public function getMetadata(): array;

}
