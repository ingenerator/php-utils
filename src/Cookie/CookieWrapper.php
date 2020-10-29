<?php


namespace Ingenerator\PHPUtils\Cookie;

use function array_merge;
use function headers_sent;
use function setcookie;

/**
 * Provides an injectable wrapper for setting and accessing cookies from other classes
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
     * @param array  $options
     */
    public function set(string $name, ?string $value = NULL, array $options = []): void
    {
        if ($this->headers_sent($file, $line)) {
            throw new HeadersSentException($name, $file, $line);
        }

        $default_options = [
            'domain' => '',
            'expires' => 0,
            'httponly'=> TRUE,
            'path' => '/',
            'secure' => $this->has_ssl_available,
        ];
        $options = array_merge($default_options, $options);

        // convert expires DateTime to timestamp
        if ($options['expires'] instanceof \DateTimeImmutable) {
            $options['expires'] = $options['expires']->getTimestamp();
        }

        // downgrade secure cookies if SSL is not available
        if (!$this->has_ssl_available) { $options['secure'] = FALSE;}

        // remove samesite option if SSL is not available
        if (!$this->has_ssl_available) { unset($options['samesite']);}

        $this->set_cookie($name, $value, $options);

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

    protected function set_cookie($name, $value, $options) { setcookie($name, $value, $options); }

    protected function headers_sent(&$file, &$line): bool
    {
        return headers_sent($file, $line);
    }
}
