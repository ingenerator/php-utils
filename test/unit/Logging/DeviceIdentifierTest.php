<?php


namespace test\unit\Ingenerator\PHPUtils\Logging;


use Ingenerator\PHPUtils\Cookie\ArrayCookieWrapperStub;
use Ingenerator\PHPUtils\Logging\DeviceIdentifier;
use Ingenerator\PHPUtils\Object\ScopeChangingCaller;
use PHPUnit\Framework\TestCase;

class DeviceIdentifierTest extends TestCase
{
    /**
     * @var \Ingenerator\PHPUtils\Cookie\ArrayCookieWrapperStub
     */
    protected $cookies;

    /**
     * @testWith [{"did": "OfLAtnofliaNqvkAjhhsFA"}]
     *           [{"didf": "OfLAtnofliaNqvkAjhhsFA"}]
     */
    public function test_its_init_does_not_set_new_cookie_if_either_cookie_present($cookie)
    {
        $this->cookies = new ArrayCookieWrapperStub($cookie);
        $this->newSubject()->init();
        $this->assertSame([], $this->cookies->inspectSetCookies());
    }

    public function test_its_init_uses_value_and_deletes_fallback_if_both_cookies_present()
    {
        $this->cookies = new ArrayCookieWrapperStub(
            [
                'didf' => 'AAAAAAAAAAAAAAAAAAAAAA',
                'did'  => 'AAAAAAAAAAAAAAAAAAAAAA',
            ]
        );
        $subject       = $this->newSubject();
        $subject->init();
        $this->assertSame('AAAAAAAAAAAAAAAAAAAAAA', $subject->getValue());
        $this->assertSame(
            [
                'didf' => [
                    [
                        'value' => '',
                        'opts'  => ['expires' => 1, 'secure' => FALSE, 'httponly' => FALSE]
                    ]
                ]
            ],
            $this->cookies->inspectSetCookies()
        );
    }

    /**
     * @testWith [{}]
     *           [{"did": "i am an abusive person!!! $$@@~~##''$$$"}]
     *           [{"did": "", "didf": ""}]
     */
    public function test_its_init_sets_new_cookies_if_not_present_or_not_valid($cookie)
    {
        $this->cookies = new ArrayCookieWrapperStub($cookie);

        $subject = $this->newSubject();
        $subject->init();

        $this->assertMatchesRegularExpression(DeviceIdentifier::VALID_REGEX, $subject->getValue());
        $cookies_set = $this->cookies->inspectSetCookies();
        $this->assertSame($subject->getValue(), $cookies_set['did'][0]['value']);
        $this->assertSame($subject->getValue(), $cookies_set['didf'][0]['value']);
    }

    public function test_it_sets_cookies_with_5_year_expiry()
    {
        $this->cookies = new ArrayCookieWrapperStub([]);
        $this->newSubject()->init();
        $expect      = new \DateTimeImmutable('+5 years');
        $cookies_set = $this->cookies->inspectSetCookies();
        $this->assertEqualsWithDelta($expect, $cookies_set['did'][0]['opts']['expires'], 1);
        $this->assertEqualsWithDelta($expect, $cookies_set['didf'][0]['opts']['expires'], 1);
    }

    public function test_it_sets_cookies_as_secure()
    {
        $this->cookies = new ArrayCookieWrapperStub([]);
        $this->newSubject()->init();

        $cookies_set = $this->cookies->inspectSetCookies();
        $this->assertSame(
            [
                'did'  => TRUE,
                'didf' => TRUE
            ],
            [
                'did'  => $cookies_set['did'][0]['opts']['secure'],
                'didf' => $cookies_set['didf'][0]['opts']['secure'],
            ]
        );
    }

    /**
     * @testWith [true, "/;SameSite=none"]
     *           [false, "/"]
     */
    public function test_it_sets_one_cookie_as_samesite_none_and_fallback_without()
    {
        $this->cookies = new ArrayCookieWrapperStub([]);
        $this->newSubject()->init();
        $cookies_set = $this->cookies->inspectSetCookies();
        $this->assertSame(
            [
                'did'  => 'none',
                'didf' => NULL
            ],
            [
                'did'  => $cookies_set['did'][0]['opts']['samesite'],
                'didf' => $cookies_set['didf'][0]['opts']['samesite']
            ]
        );
    }

    /**
     * @testWith [{}, "-unset-"]
     *           [{"did": ""}, "-unset-"]
     *           [{"didf": ""}, "-unset-"]
     *           [{"did": "OfLAtnofliaNqvkAjhhsFA"}, "OfLAtnofliaNqvkAjhhsFA"]
     *           [{"didf": "OfLAtnofliaNqvkAjhhsFA"}, "OfLAtnofliaNqvkAjhhsFA"]
     *           [{"did": "OfLAtnofliaNqvkAjhhsFA", "didf": "OfLAtnofliaNqvkAjhhsFA"}, "OfLAtnofliaNqvkAjhhsFA"]
     *           [{"did": "i am an abusive person!!! $$@@~~##''$$$"}, "-invalid-"]
     */
    public function test_its_getter_returns_valid_cookie_value_or_unset_if_initialised_never_called(
        $cookie,
        $expect
    ) {
        $this->cookies = new ArrayCookieWrapperStub($cookie);
        $this->assertSame($expect, $this->newSubject()->getValue());
    }

    public function test_its_getter_returns_initialised_value_if_initialised()
    {
        $this->cookies = new ArrayCookieWrapperStub([]);
        $subj          = $this->newSubject();
        $subj->init();
        $value = $subj->getValue();
        $this->assertMatchesRegularExpression(DeviceIdentifier::VALID_REGEX, $value);
    }

    public function test_its_static_init_creates_instance_and_initialises()
    {
        ScopeChangingCaller::call(
            DeviceIdentifier::class,
            function () { DeviceIdentifier::$instance = NULL; }
        );

        $old_cookie = $_COOKIE;
        try {
            $_COOKIE['did'] = '1234567890123456789012';
            DeviceIdentifier::initAndEnsureCookieSet(TRUE, FALSE);
            $this->assertSame('1234567890123456789012', DeviceIdentifier::get());

            // Note that here the init method has assigned a global instance so changes to $_COOKIE
            // don't do anything
            $_COOKIE['did'] = '12345678901234567890AA';
            $this->assertSame('1234567890123456789012', DeviceIdentifier::get());

        } finally {
            $_COOKIE = $old_cookie;
        }
        $this->assertSame('1234567890123456789012', DeviceIdentifier::get());
    }

    public function test_its_static_init_creates_instance_and_initialises_with_fixed_id_in_cli()
    {
        ScopeChangingCaller::call(
            DeviceIdentifier::class,
            function () { DeviceIdentifier::$instance = NULL; }
        );

        DeviceIdentifier::initAndEnsureCookieSet(TRUE); // Fall back to default arg
        $this->assertSame(DeviceIdentifier::CLI_ID, DeviceIdentifier::get());
        $this->assertMatchesRegularExpression(DeviceIdentifier::VALID_REGEX, DeviceIdentifier::get());
    }

    public function test_its_static_get_can_read_from_cookies_even_if_not_initialised()
    {
        ScopeChangingCaller::call(
            DeviceIdentifier::class,
            function () { DeviceIdentifier::$instance = NULL; }
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

    public function test_its_force_test_value_forces_value_without_setting_cookies()
    {
        DeviceIdentifier::forceGlobalTestValue('abc4567890123456789012');
        $this->assertSame('abc4567890123456789012', DeviceIdentifier::get());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->cookies = new ArrayCookieWrapperStub;
    }

    protected function newSubject()
    {
        return ScopeChangingCaller::call(
            DeviceIdentifier::class,
            function ($cookies) { return new DeviceIdentifier($cookies); },
            $this->cookies
        );
    }

}
