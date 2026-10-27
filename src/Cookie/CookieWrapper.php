<?php

namespace Ingenerator\PHPUtils\Cookie;

use DateTimeImmutable;
use UnexpectedValueException;

use function array_merge;
use function headers_sent;
use function setcookie;

/**
 * Provides an injectable wrapper for setting and accessing cookies from other classes.
 *
 * This includes:
 *  - Actually setting cookies, with sane defaults
 *  - Automatically downgrading secure cookies and dropping SameSite when SSL is not available
 *    (assumed to be a dev env)
 *  - Accessing the current value of a cookie whether it was provided by the client (in $_COOKIE) or
 *    set server-side
 *  - Throwing an exception if the cookie could not be set because headers have been sent
 *
 * See also ArrayCookieWrapperStub for a simulated implementation that doesn't actually attempt to
 * set cookies, for use in unit tests.
 */
class CookieWrapper
{
    /**
     * Note: PHP $_COOKIE is normally array<string,string> however:
     *  - It can contain arrays following the same syntax as form-encoded fields e.g. a cookie named
     *    `foo[bar]` with value `ok` will produce a $_COOKIE like ['foo' => ['bar' => 'ok']]
     *  - It can have any level of nested array structure
     *  - Nested array keys can be ints e.g. cookie `foo[5]` will produce ['foo' => [5 => 'ok']]
     *
     * However we only support returning string values.
     *
     * @var array<mixed>
     */
    protected array $cookies = [];

    public function __construct(protected bool $has_ssl_available = true)
    {
        $this->cookies = $_COOKIE;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function set(string $name, string $value = '', array $options = []): void
    {
        if ($this->headers_sent($file, $line)) {
            throw new HeadersSentException($name, $file, $line);
        }

        $default_options = [
            'domain' => '',
            'expires' => 0,
            'httponly' => true,
            'path' => '/',
            'secure' => $this->has_ssl_available,
        ];
        $options = array_merge($default_options, $options);

        // convert expires DateTime to timestamp
        if ($options['expires'] instanceof DateTimeImmutable) {
            $options['expires'] = $options['expires']->getTimestamp();
        }

        // downgrade secure cookies if SSL is not available
        if (!$this->has_ssl_available) {
            $options['secure'] = false;
        }

        // remove samesite option if SSL is not available
        if (!$this->has_ssl_available) {
            unset($options['samesite']);
        }

        $this->set_cookie($name, $value, $options);

        $this->cookies[$name] = $value;
    }

    public function get(string $name): ?string
    {
        if (!isset($this->cookies[$name])) {
            return null;
        }

        $value = $this->cookies[$name];

        if (is_string($value)) {
            return $value;
        }

        throw new UnexpectedValueException(
            sprintf('Cookie named %s should have been string, got %s', $name, get_debug_type($value))
        );
    }

    public function has(string $name): bool
    {
        return isset($this->cookies[$name]);
    }

    public function delete(string $name): void
    {
        $this->set(
            $name,
            '',
            [
                'expires' => 1,
                'secure' => false,
                'httponly' => false,
            ]
        );
        unset($this->cookies[$name]);
    }

    /** @param array<string, mixed> $options */
    protected function set_cookie(string $name, string $value, array $options): void
    {
        $cookie_options = [
            'expires' => is_int($options['expires'] ?? null) ? $options['expires'] : 0,
            'path' => is_string($options['path'] ?? null) ? $options['path'] : '/',
            'domain' => is_string($options['domain'] ?? null) ? $options['domain'] : '',
            'secure' => (bool) ($options['secure'] ?? false),
            'httponly' => (bool) ($options['httponly'] ?? false),
        ];
        $samesite = $options['samesite'] ?? null;
        if (in_array($samesite, ['Lax', 'lax', 'None', 'none', 'Strict', 'strict'], true)) {
            $cookie_options['samesite'] = $samesite;
        }

        setcookie($name, $value, $cookie_options);
    }

    protected function headers_sent(?string &$file = null, ?int &$line = null): bool
    {
        return headers_sent($file, $line);
    }
}
