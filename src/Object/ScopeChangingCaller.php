<?php


namespace Ingenerator\PHPUtils\Object;


class ScopeChangingCaller
{

    /**
     * Shorthand to call a function in the scope of another class
     *
     * Use e.g. to create instances with private/protected constructors for testing:
     *
     *   return ScopeChangingCaller::call(
     *       SomeCautious::class,
     *       function ($smthng) { return new SomeCautious($smthng); },
     *       $this->cookies
     *   );
     *
     * Or to reset an internal static property:
     *
     *   ScopeChangingCaller::call(
     *       ACachedThing::class,
     *       function () { ACachedThing::$cache = NULL; }
     *   );
     *
     * @param string   $class
     * @param callable $func
     * @param mixed    ...$args
     *
     * @return mixed
     */
    public static function call(string $class, callable $func, ...$args)
    {
        $func = \Closure::bind($func, NULL, $class);

        return $func(...$args);
    }
}
