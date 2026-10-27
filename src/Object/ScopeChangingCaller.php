<?php

namespace Ingenerator\PHPUtils\Object;

use Closure;
use LogicException;

class ScopeChangingCaller
{
    /**
     * Shorthand to call a function in the scope of another class.
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
     * @template TReturn of mixed
     * @template TArgs
     *
     * @param class-string $class
     * @param Closure(TArgs):TReturn $func
     * @param TArgs $args,...
     *
     * @return TReturn
     */
    public static function call(string $class, Closure $func, mixed ...$args): mixed
    {
        $func = Closure::bind($func, null, $class)
            ?? throw new LogicException('Unable to bind closure to requested scope');

        return $func(...$args);
    }
}
