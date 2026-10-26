<?php

declare(strict_types=1);

namespace test\unit\Ingenerator\PHPUtils\Logging;

use DateTimeImmutable;
use Ingenerator\PHPUtils\Cookie\ArrayCookieWrapperStub;
use Ingenerator\PHPUtils\Cookie\CookieWrapper;
use Ingenerator\PHPUtils\Logging\DeviceIdentifier;
use Ingenerator\PHPUtils\Object\ScopeChangingCaller;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class DeviceIdentifierTest extends TestCase
{
    private ArrayCookieWrapperStub $cookies;

    /** @param array<string, string> $cookie */
    #[TestWith([['did' => 'OfLAtnofliaNqvkAjhhsFA']])]
    #[TestWith([['didf' => 'OfLAtnofliaNqvkAjhhsFA']])]
    public function testItsInitDoesNotSetNewCookieIfEitherCookiePresent(array $cookie): void
    {
        $this->cookies = new ArrayCookieWrapperStub($cookie);
        $this->newSubject()->init();
        $this->assertSame([], $this->cookies->inspectSetCookies());
    }

    public function testItsInitUsesValueAndDeletesFallbackIfBothCookiesPresent(): void
    {
        $this->cookies = new ArrayCookieWrapperStub(
            [
                'didf' => 'AAAAAAAAAAAAAAAAAAAAAA',
                'did' => 'AAAAAAAAAAAAAAAAAAAAAA',
            ]
        );
        $subject = $this->newSubject();
        $subject->init();
        $this->assertSame('AAAAAAAAAAAAAAAAAAAAAA', $subject->getValue());
        $this->assertSame(
            [
                'didf' => [
                    [
                        'value' => '',
                        'opts' => ['expires' => 1, 'secure' => false, 'httponly' => false],
                    ],
                ],
            ],
            $this->cookies->inspectSetCookies()
        );
    }

    /**
     * @param array<string, string> $cookie
     */
    #[TestWith([[]])]
    #[TestWith([['did' => "i am an abusive person!!! \$\$@@~~##''\$\$\$"]])]
    #[TestWith([['did' => '', 'didf' => '']])]
    public function testItsInitSetsNewCookiesIfNotPresentOrNotValid(array $cookie): void
    {
        $this->cookies = new ArrayCookieWrapperStub($cookie);

        $subject = $this->newSubject();
        $subject->init();

        $this->assertMatchesRegularExpression(DeviceIdentifier::VALID_REGEX, $subject->getValue());
        $cookies_set = $this->cookies->inspectSetCookies();
        $this->assertSame($subject->getValue(), $cookies_set['did'][0]['value']);
        $this->assertSame($subject->getValue(), $cookies_set['didf'][0]['value']);
    }

    public function testItSetsCookiesWith5YearExpiry(): void
    {
        $this->cookies = new ArrayCookieWrapperStub([]);
        $this->newSubject()->init();
        $expect = new DateTimeImmutable('+5 years');
        $cookies_set = $this->cookies->inspectSetCookies();
        $this->assertEqualsWithDelta($expect, $cookies_set['did'][0]['opts']['expires'], 1);
        $this->assertEqualsWithDelta($expect, $cookies_set['didf'][0]['opts']['expires'], 1);
    }

    public function testItSetsCookiesAsSecure(): void
    {
        $this->cookies = new ArrayCookieWrapperStub([]);
        $this->newSubject()->init();

        $cookies_set = $this->cookies->inspectSetCookies();
        $this->assertSame(
            [
                'did' => true,
                'didf' => true,
            ],
            [
                'did' => $cookies_set['did'][0]['opts']['secure'],
                'didf' => $cookies_set['didf'][0]['opts']['secure'],
            ]
        );
    }

    #[TestWith([true, '/;SameSite=none'])]
    #[TestWith([false, '/'])]
    public function testItSetsOneCookieAsSamesiteNoneAndFallbackWithout(): void
    {
        $this->cookies = new ArrayCookieWrapperStub([]);
        $this->newSubject()->init();
        $cookies_set = $this->cookies->inspectSetCookies();
        $this->assertSame(
            [
                'did' => 'none',
                'didf' => null,
            ],
            [
                'did' => $cookies_set['did'][0]['opts']['samesite'],
                'didf' => $cookies_set['didf'][0]['opts']['samesite'],
            ]
        );
    }

    /**
     * @param array<string, string> $cookie
     */
    #[TestWith([[], '-unset-'])]
    #[TestWith([['did' => ''], '-unset-'])]
    #[TestWith([['didf' => ''], '-unset-'])]
    #[TestWith([['did' => 'OfLAtnofliaNqvkAjhhsFA'], 'OfLAtnofliaNqvkAjhhsFA'])]
    #[TestWith([['didf' => 'OfLAtnofliaNqvkAjhhsFA'], 'OfLAtnofliaNqvkAjhhsFA'])]
    #[TestWith([['did' => 'OfLAtnofliaNqvkAjhhsFA', 'didf' => 'OfLAtnofliaNqvkAjhhsFA'], 'OfLAtnofliaNqvkAjhhsFA'])]
    #[TestWith([['did' => "i am an abusive person!!! \$\$@@~~##''\$\$\$"], '-invalid-'])]
    public function testItsGetterReturnsValidCookieValueOrUnsetIfInitialisedNeverCalled(
        array $cookie,
        string $expect,
    ): void {
        $this->cookies = new ArrayCookieWrapperStub($cookie);
        $this->assertSame($expect, $this->newSubject()->getValue());
    }

    public function testItsGetterReturnsInitialisedValueIfInitialised(): void
    {
        $this->cookies = new ArrayCookieWrapperStub([]);
        $subj = $this->newSubject();
        $subj->init();
        $value = $subj->getValue();
        $this->assertMatchesRegularExpression(DeviceIdentifier::VALID_REGEX, $value);
    }

    public function testItsStaticInitCreatesInstanceAndInitialises(): void
    {
        ScopeChangingCaller::call(
            DeviceIdentifier::class,
            /** @phpstan-ignore staticProperty.protected (intentional modification of state to reset singleton) */
            function (): void { DeviceIdentifier::$instance = null; }
        );

        $old_cookie = $_COOKIE;
        try {
            $_COOKIE['did'] = '1234567890123456789012';
            DeviceIdentifier::initAndEnsureCookieSet(true, false);
            $this->assertSame('1234567890123456789012', DeviceIdentifier::get());

            // Note that here the init method has assigned a global instance so changes to $_COOKIE
            // don't do anything
            $_COOKIE['did'] = '12345678901234567890AA';
            /* @phpstan-ignore method.alreadyNarrowedType (PHPStan can already tell this passes but I'd like to prove it again) */
            $this->assertSame('1234567890123456789012', DeviceIdentifier::get());
        } finally {
            $_COOKIE = $old_cookie;
        }

        /* @phpstan-ignore method.alreadyNarrowedType (PHPStan can already tell this passes but I'd like to prove it again) */
        $this->assertSame('1234567890123456789012', DeviceIdentifier::get());
    }

    public function testItsStaticInitCreatesInstanceAndInitialisesWithFixedIdInCli(): void
    {
        ScopeChangingCaller::call(
            DeviceIdentifier::class,
            /** @phpstan-ignore staticProperty.protected (intentional modification of state to reset singleton) */
            function (): void { DeviceIdentifier::$instance = null; }
        );

        DeviceIdentifier::initAndEnsureCookieSet(true); // Fall back to default arg
        $this->assertSame(DeviceIdentifier::CLI_ID, DeviceIdentifier::get());
        $this->assertMatchesRegularExpression(DeviceIdentifier::VALID_REGEX, DeviceIdentifier::get());
    }

    public function testItsStaticGetCanReadFromCookiesEvenIfNotInitialised(): void
    {
        ScopeChangingCaller::call(
            DeviceIdentifier::class,
            /** @phpstan-ignore staticProperty.protected (intentional modification of state to reset singleton) */
            function (): void { DeviceIdentifier::$instance = null; }
        );

        $old_cookie = $_COOKIE;
        try {
            $_COOKIE['did'] = '1234567890123456789012';
            $this->assertSame('1234567890123456789012', DeviceIdentifier::get());

            // Note that here init was never called so the get method reads from cookie each time
            // without attempting to set any state
            $_COOKIE['did'] = '12345678901234567890AA';
            $this->assertSame('12345678901234567890AA', DeviceIdentifier::get());
        } finally {
            $_COOKIE = $old_cookie;
        }
    }

    public function testItsForceTestValueForcesValueWithoutSettingCookies(): void
    {
        DeviceIdentifier::forceGlobalTestValue('abc4567890123456789012');
        $this->assertSame('abc4567890123456789012', DeviceIdentifier::get());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->cookies = new ArrayCookieWrapperStub();
    }

    protected function newSubject(): DeviceIdentifier
    {
        return ScopeChangingCaller::call(
            DeviceIdentifier::class,
            /** @phpstan-ignore new.protectedConstructor (intentional modification of state to reset singleton) */
            fn (CookieWrapper $cookies): DeviceIdentifier => new DeviceIdentifier($cookies),
            $this->cookies
        );
    }
}
