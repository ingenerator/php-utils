<?php


namespace test\unit\Ingenerator\PHPUtils\Cookie;


use Ingenerator\PHPUtils\Cookie\CookieWrapper;
use Ingenerator\PHPUtils\Cookie\HeadersSentException;
use PHPUnit\Framework\TestCase;

class CookieWrapperTest extends TestCase
{

    protected $_old_cookie_var;

    protected $ssl_available = TRUE;

    /**
     * @testWith ["my", true, "cookie"]
     *           ["has", true, "values"]
     *           ["anything", false, null]
     */
    public function test_it_has_cookies_from_superglobal($name, $expect_has, $expect_val)
    {
        $_COOKIE = ['my' => 'cookie', 'has' => 'values'];
        $subject = $this->newSubject();
        $this->assertSame(
            [
                'has'   => $expect_has,
                'value' => $expect_val,
            ],
            [
                'has'   => $subject->has($name),
                'value' => $subject->get($name),
            ]
        );
    }

    public function test_it_has_cookies_that_have_been_set_during_request()
    {
        $_COOKIE = [];
        $subject = $this->newSubject();
        $subject->set('anything', 'any value');
        $this->assertSame(
            [
                'has'   => TRUE,
                'value' => 'any value',
            ],
            [
                'has'   => $subject->has('anything'),
                'value' => $subject->get('anything'),
            ]
        );
    }

    public function test_it_throws_from_set_cookie_if_headers_have_been_sent()
    {
        $subject               = $this->newSubject();
        $subject->headers_sent = ['file' => 'myfile.php', 'line' => 152];
        $this->expectException(HeadersSentException::class);
        $this->expectExceptionMessage(
            'Cannot assign cookie foo - headers have been sent by output at myfile.php:152'
        );
        $subject->set('foo', 'anything');
    }

    public function test_it_sets_cookies_using_provided_options()
    {
        $this->ssl_available = TRUE;
        $subject             = $this->newSubject();
        $subject->set(
            'anything',
            'any value',
            [
                'expires'  => 1600952284,
                'path'     => '/somedir',
                'domain'   => 'foo.bar.com',
                'secure'   => FALSE,
                'httponly' => FALSE,
                'samesite' => NULL
            ]
        );

        $this->assertSame(
            [
                [
                    'name'     => 'anything',
                    'value'    => 'any value',
                    'expires'  => 1600952284,
                    'path'     => '/somedir',
                    'domain'   => 'foo.bar.com',
                    'secure'   => FALSE,
                    'httponly' => FALSE,
                ]
            ],
            $subject->setcookie_calls
        );
    }

    public function test_it_applies_sane_defaults_when_setting_cookies()
    {
        $this->ssl_available = TRUE;
        $subject             = $this->newSubject();
        $subject->set('somecookie', 'some stuff');

        $this->assertSame(
            [
                [
                    'name'     => 'somecookie',
                    'value'    => 'some stuff',
                    'expires'  => 0,
                    'path'     => '/',
                    'domain'   => '',
                    'secure'   => TRUE,
                    'httponly' => TRUE,
                ]
            ],
            $subject->setcookie_calls
        );
    }

    public function test_it_can_set_expires_from_datetime_immutable()
    {
        $subject = $this->newSubject();
        $subject->set(
            'any',
            'thing',
            ['expires' => new \DateTimeImmutable('2028-03-02 10:02:02 +01:00')]
        );
        $this->assertSame(
            1835600522,
            $subject->setcookie_calls[0]['expires']
        );
    }

    /**
     * @testWith [{"secure": true}, false]
     *           [{"secure": false}, false]
     *           [{}, false]
     */
    public function test_it_downgrades_secure_cookies_when_no_ssl_available($opts, $expect)
    {
        $this->ssl_available = FALSE;
        $subject             = $this->newSubject();
        $subject->set('whatever', 'things', $opts);

        $this->assertSame($expect, $subject->setcookie_calls[0]['secure']);
    }

    /**
     * @testWith [true, {}, "/"]
     *           [true, {"samesite": "Lax"}, "/;SameSite=Lax"]
     *           [true, {"samesite": "None"}, "/;SameSite=None"]
     *           [true, {"path": "/wierdo", "samesite": "None"}, "/wierdo;SameSite=None"]
     *           [false, {"samesite": "None"}, "/"]
     */
    public function test_it_adds_samesite_attribute_only_if_ssl_available($has_ssl, $opts, $expect)
    {
        $this->ssl_available = $has_ssl;
        $subject             = $this->newSubject();
        $subject->set('whatever', 'things', $opts);

        $this->assertSame($expect, $subject->setcookie_calls[0]['path']);
    }

    public function test_it_deletes_cookie_by_setting_value_empty_and_expires_in_past()
    {
        $_COOKIE['myvar'] = 'whoopsy';
        $subject          = $this->newSubject();
        $subject->delete('myvar');
        $this->assertSame(
            [
                [
                    'name'     => 'myvar',
                    'value'    => '',
                    'expires'  => 1,
                    'path'     => '/',
                    'domain'   => '',
                    'secure'   => FALSE,
                    'httponly' => FALSE,
                ]
            ],
            $subject->setcookie_calls
        );
        $this->assertSame(
            [
                'has'   => FALSE,
                'value' => NULL,
            ],
            [
                'has'   => $subject->has('myvar'),
                'value' => $subject->get('myvar'),
            ]
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->_old_cookie_var = $_COOKIE;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $_COOKIE = $this->_old_cookie_var;
    }

    protected function newSubject()
    {
        return new TestableCookieWrapper($this->ssl_available);
    }
}

class TestableCookieWrapper extends CookieWrapper
{
    public $setcookie_calls = [];
    public $headers_sent = FALSE;

    protected function set_cookie(
        $name,
        $value,
        $expires,
        $path,
        $domain,
        $secure,
        $httponly
    ): void {
        $this->setcookie_calls[] = [
            'name'     => $name,
            'value'    => $value,
            'expires'  => $expires,
            'path'     => $path,
            'domain'   => $domain,
            'secure'   => $secure,
            'httponly' => $httponly
        ];
    }

    protected function headers_sent(&$file, &$line): bool
    {
        if ($this->headers_sent === FALSE) {
            return FALSE;
        }

        $file = $this->headers_sent['file'];
        $line = $this->headers_sent['line'];

        return TRUE;
    }
}
