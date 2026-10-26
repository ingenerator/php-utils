<?php

declare(strict_types=1);

namespace test\unit\Ingenerator\PHPUtils\Cookie;

use DateTimeImmutable;
use Ingenerator\PHPUtils\Cookie\CookieWrapper;
use Ingenerator\PHPUtils\Cookie\HeadersSentException;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class CookieWrapperTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $_old_cookie_var;

    private bool $ssl_available = true;

    #[TestWith(['my', true, 'cookie'])]
    #[TestWith(['has', true, 'values'])]
    #[TestWith(['anything', false, null])]
    public function testItHasCookiesFromSuperglobal(string $name, bool $expect_has, ?string $expect_val): void
    {
        $_COOKIE = ['my' => 'cookie', 'has' => 'values'];
        $subject = $this->newSubject();
        $this->assertSame(
            [
                'has' => $expect_has,
                'value' => $expect_val,
            ],
            [
                'has' => $subject->has($name),
                'value' => $subject->get($name),
            ]
        );
    }

    public function testItHasCookiesThatHaveBeenSetDuringRequest(): void
    {
        $_COOKIE = [];
        $subject = $this->newSubject();
        $subject->set('anything', 'any value');
        $this->assertSame(
            [
                'has' => true,
                'value' => 'any value',
            ],
            [
                'has' => $subject->has('anything'),
                'value' => $subject->get('anything'),
            ]
        );
    }

    public function testItThrowsFromSetCookieIfHeadersHaveBeenSent(): void
    {
        $subject = $this->newSubject();
        $subject->headers_sent = ['file' => 'myfile.php', 'line' => 152];
        $this->expectException(HeadersSentException::class);
        $this->expectExceptionMessage(
            'Cannot assign cookie foo - headers have been sent by output at myfile.php:152'
        );
        $subject->set('foo', 'anything');
    }

    public function testItSetsCookiesUsingProvidedOptions(): void
    {
        $this->ssl_available = true;
        $subject = $this->newSubject();
        $subject->set(
            'anything',
            'any value',
            [
                'expires' => 1600952284,
                'path' => '/somedir',
                'domain' => 'foo.bar.com',
                'secure' => false,
                'httponly' => false,
            ]
        );

        $this->assertSame(
            [
                [
                    'name' => 'anything',
                    'value' => 'any value',
                    'options' => [
                        'domain' => 'foo.bar.com',
                        'expires' => 1600952284,
                        'httponly' => false,
                        'path' => '/somedir',
                        'secure' => false,
                    ],
                ],
            ],
            $subject->setcookie_calls
        );
    }

    public function testItAppliesSaneDefaultsWhenSettingCookies(): void
    {
        $this->ssl_available = true;
        $subject = $this->newSubject();
        $subject->set('somecookie', 'some stuff');

        $this->assertSame(
            [
                [
                    'name' => 'somecookie',
                    'value' => 'some stuff',
                    'options' => [
                        'domain' => '',
                        'expires' => 0,
                        'httponly' => true,
                        'path' => '/',
                        'secure' => true,
                    ],
                ],
            ],
            $subject->setcookie_calls
        );
    }

    public function testItCanSetExpiresFromDatetimeImmutable(): void
    {
        $subject = $this->newSubject();
        $subject->set(
            'any',
            'thing',
            ['expires' => new DateTimeImmutable('2028-03-02 10:02:02 +01:00')]
        );
        $this->assertSame(
            1835600522,
            $subject->setcookie_calls[0]['options']['expires']
        );
    }

    /** @param array<string, mixed> $opts */
    #[TestWith([['secure' => true], false])]
    #[TestWith([['secure' => false], false])]
    #[TestWith([[], false])]
    public function testItDowngradesSecureCookiesWhenNoSslAvailable(array $opts, bool $expect): void
    {
        $this->ssl_available = false;
        $subject = $this->newSubject();
        $subject->set('whatever', 'things', $opts);

        $this->assertSame($expect, $subject->setcookie_calls[0]['options']['secure']);
    }

    /** @param array<string, mixed> $opts */
    #[TestWith([true, [], null])]
    #[TestWith([true, ['samesite' => 'Lax'], 'Lax'])]
    #[TestWith([true, ['samesite' => 'None'], 'None'])]
    #[TestWith([false, ['samesite' => 'None'], null])]
    public function testItAddsSamesiteAttributeOnlyIfSslAvailable(bool $has_ssl, array $opts, ?string $expect): void
    {
        $this->ssl_available = $has_ssl;
        $subject = $this->newSubject();
        $subject->set('whatever', 'things', $opts);

        if ($expect === null) {
            $this->assertArrayNotHasKey('samesite', $subject->setcookie_calls[0]['options']);
        } else {
            $this->assertSame($expect, $subject->setcookie_calls[0]['options']['samesite']);
        }
    }

    public function testItDeletesCookieBySettingValueEmptyAndExpiresInPast(): void
    {
        $_COOKIE['myvar'] = 'whoopsy';
        $subject = $this->newSubject();
        $subject->delete('myvar');
        $this->assertSame(
            [
                [
                    'name' => 'myvar',
                    'value' => '',
                    'options' => [
                        'domain' => '',
                        'expires' => 1,
                        'httponly' => false,
                        'path' => '/',
                        'secure' => false,
                    ],
                ],
            ],
            $subject->setcookie_calls
        );
        $this->assertSame(
            [
                'has' => false,
                'value' => null,
            ],
            [
                'has' => $subject->has('myvar'),
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

    protected function newSubject(): TestableCookieWrapper
    {
        return new TestableCookieWrapper($this->ssl_available);
    }
}

class TestableCookieWrapper extends CookieWrapper
{
    public $setcookie_calls = [];
    public $headers_sent = false;

    protected function set_cookie(
        $name,
        $value,
        $options,
    ): void {
        $this->setcookie_calls[] = [
            'name' => $name,
            'value' => $value,
            'options' => $options,
        ];
    }

    protected function headers_sent(&$file, &$line): bool
    {
        if ($this->headers_sent === false) {
            return false;
        }

        $file = $this->headers_sent['file'];
        $line = $this->headers_sent['line'];

        return true;
    }
}
