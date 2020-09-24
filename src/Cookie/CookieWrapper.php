<?php


namespace Ingenerator\PHPUtils\Cookie;

/**
 * Provides an injectable wrapper for setting and accessing cookies from other classes
 *
 * This includes:
 *  - Actually setting cookies, with sane defaults and samesite support in php < 7.3
 *  - Automatically downgrading secure cookies and dropping SameSite when SSL is not available
 *    (assumed to be a dev env)
 *  - Accessing the current value of a cookie whether it was provided by the client (in $_COOKIE) or
 *    set server-side
 *  - Throwing an exception if the cookie could not be set because headers have been sent
 *
 * See also ArrayCookieWrapperStub for a simulated implementation that doesn't actually attempt to
 * set cookies, for use in unit tests.
 *
 */
class CookieWrapper
{

    /**
     * @var mixed[]
     */
    protected $cookies = [];

    /**
     * @var boolean
     */
    protected $has_ssl_available;

    public function __construct(bool $has_ssl_available = TRUE)
    {
        $this->cookies           = $_COOKIE;
        $this->has_ssl_available = $has_ssl_available;
    }

    /**
     * @param string $name
     * @param string $value
     * @param int    $expire
     * @param string $path
     * @param string $domain
     * @param bool   $secure Note this will be toggled off if running in a dev/CI environment without SSL
     * @param bool   $httponly
     */
    public function set(string $name, ?string $value = NULL, array $options = []): void
    {
        if ($this->headers_sent($file, $line)) {
            throw new HeadersSentException($name, $file, $line);
        }

        $path = $options['path'] ?? '/';
        if ($this->has_ssl_available and ($options['samesite'] ?? NULL)) {
            $path .= ';SameSite='.$options['samesite'];
        }

        $expires = $options['expires'] ?? 0;
        if ($expires instanceof \DateTimeImmutable) {
            $expires = $expires->getTimestamp();
        }

        $this->set_cookie(
            $name,
            $value,
            $expires,
            $path,
            $options['domain'] ?? '',
            $this->has_ssl_available && ($options['secure'] ?? TRUE),
            $options['httponly'] ?? TRUE
        );

        $this->cookies[$name] = $value;
    }

    public function get(string $name): ?string
    {
        return $this->cookies[$name] ?? NULL;
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
                'expires'  => 1,
                'secure'   => FALSE,
                'httponly' => FALSE
            ]
        );
        unset($this->cookies[$name]);
    }

    protected function set_cookie(
        $name,
        $value,
        $expires,
        $path,
        $domain,
        $secure,
        $httponly
    ): void {
        \setcookie($name, $value, $expires, $path, $domain, $secure, $httponly);
    }

    protected function headers_sent(&$file, &$line): bool
    {
        return headers_sent($file, $line);
    }
}
