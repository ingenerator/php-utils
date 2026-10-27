<?php

declare(strict_types=1);

namespace test\unit\Ingenerator\PHPUtils\Cookie;

use DateTimeImmutable;
use Ingenerator\PHPUtils\Cookie\ArrayCookieWrapperStub;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class ArrayCookieWrapperStubTest extends TestCase
{
    #[TestWith(['my', true, 'cookie'])]
    #[TestWith(['has', true, 'values'])]
    #[TestWith(['anything', false, null])]
    public function testItHasCookiesFromConstructorIgnoringSuperglobal(
        string $name,
        bool $expect_has,
        ?string $expect_val,
    ): void {
        $old_cookie = $_COOKIE;
        try {
            $_COOKIE = ['anything' => 'whoops'];
            $subject = $this->newSubject(['my' => 'cookie', 'has' => 'values']);
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
        } finally {
            $_COOKIE = $old_cookie;
        }
    }

    public function testItCanSimulateSetCookieAndCaptureArgs(): void
    {
        $exp = new DateTimeImmutable('tomorrow');
        $subject = $this->newSubject();
        $subject->set('any', 'thing', ['expires' => $exp]);
        $this->assertSame(
            [
                'has' => true,
                'value' => 'thing',
            ],
            [
                'has' => $subject->has('any'),
                'value' => $subject->get('any'),
            ]
        );
        $this->assertSame(
            ['any' => [['value' => 'thing', 'opts' => ['expires' => $exp]]]],
            $subject->inspectSetCookies()
        );
    }

    /** @param array<string, mixed> $cookies */
    protected function newSubject(array $cookies = []): ArrayCookieWrapperStub
    {
        return new ArrayCookieWrapperStub($cookies);
    }
}
