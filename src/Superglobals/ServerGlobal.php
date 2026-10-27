<?php

namespace Ingenerator\PHPUtils\Superglobals;

final class ServerGlobal
{
    /**
     * @var array<string, mixed>
     */
    private ?array $server = null;

    /**
     * @param array<string, mixed> $server
     */
    public static function fake(array $server): self
    {
        $i = new self();
        $i->server = $server;

        return $i;
    }

    public function getString(string $key): ?string
    {
        $server = $this->server ?? $_SERVER;
        $value = $server[$key] ?? null;
        // Assume that this is only called with keys that are known to be guaranteed to be string e.g.
        // - REMOTE_ADDRESS
        // - HTTP_XXX (multiple headers are combined to a comma-separated string, not an array
        // We therefore don't need to do runtime type checks here (the strict return type will cause a TypeError
        // at runtime if the user calls with one of the few values that are allowed not to be strings.
        assert($value === null || is_string($value), 'Expected '.$key.' to be a string, got '.get_debug_type($value));

        return $value;
    }
}
