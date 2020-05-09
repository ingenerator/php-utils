<?php

namespace Ingenerator\PHPUtils\Object;

/**
 * Provides the core implementation required to add singleton functionality to any object that needs to be initialised
 *
 * @package Ingenerator\PHPUtils\Object
 */
trait  InitialisableSingletonTrait
{
    private static $instance;

    /**
     * Return the singleton instance
     *
     * @return object
     * @throws SingletonNotInitialisedException if the class has not been initialised
     */
    public static function instance()
    {
        if ( ! static::$instance) {
            throw new SingletonNotInitialisedException('Cannot access '.static::class.' - class not initialised');
        }

        return static::$instance;
    }

    /**
     * Initialises the singleton
     *
     * Takes a callable that creates the instance and configures it as required, then returns it for storage. This
     * allows for different constructor mechanisms / dependencies for different classes.
     *
     * @param callable $initialiser
     *
     * @throws \LogicException if the singleton is already initialised
     */
    public static function initialise(callable $initialiser): void
    {
        if (static::$instance) {
            throw new \LogicException('Cannot re-initialise '.static::class);
        }

        $instance     = $initialiser();
        $expect_class = static::class;
        if ( ! $instance instanceof $expect_class) {
            throw new \InvalidArgumentException(
                'Initialiser for '.static::class.' should return instance but did not get one'
            );
        }

        static::$instance = $instance;
    }

    /**
     * Indicates if the singleton is initialised
     *
     * @return bool
     */
    public static function isInitialised(): bool
    {
        return (bool) static::$instance;
    }

}
