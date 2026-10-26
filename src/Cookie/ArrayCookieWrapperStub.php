<?php

namespace Ingenerator\PHPUtils\Cookie;

class ArrayCookieWrapperStub extends CookieWrapper
{
    /** @var array<string, list<array{value: ?string, opts: array<string, mixed>}>> */
    protected $set_cookie_calls = [];

    /** @param array<string, mixed> $cookies */
    public function __construct(array $cookies = [])
    {
        parent::__construct(has_ssl_available: false);
        $this->cookies = $cookies;
    }

    public function set(string $name, ?string $value = null, array $options = []): void
    {
        parent::set($name, $value, $options);
        $this->set_cookie_calls[$name][] = ['value' => $value, 'opts' => $options];
    }

    /** @param array<string, mixed> $options */
    protected function set_cookie(string $name, ?string $value, array $options): void
    {
        // No-op
    }

    protected function headers_sent(?string &$file = null, ?int &$line = null): bool
    {
        return false;
    }

    /** @return array<string, list<array{value: ?string, opts: array<string, mixed>}>> */
    public function inspectSetCookies(): array
    {
        return $this->set_cookie_calls;
    }
}
