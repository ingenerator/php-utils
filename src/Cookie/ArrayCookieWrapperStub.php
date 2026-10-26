<?php

namespace Ingenerator\PHPUtils\Cookie;

class ArrayCookieWrapperStub extends CookieWrapper
{
    protected $set_cookie_calls = [];

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

    protected function set_cookie($name, $value, $options): void
    {
        // No-op
    }

    protected function headers_sent(&$file, &$line): bool
    {
        return false;
    }

    public function inspectSetCookies(): array
    {
        return $this->set_cookie_calls;
    }
}
