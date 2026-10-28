<?php

declare(strict_types=1);

namespace Ingenerator\PHPUtils\Logging;

use DateInterval;
use DateTimeImmutable;
use Ingenerator\PHPUtils\Cookie\ArrayCookieWrapperStub;
use Ingenerator\PHPUtils\Cookie\CookieWrapper;
use Ingenerator\PHPUtils\StringEncoding\Base64Url;

use function preg_match;
use function random_bytes;

/**
 * Allocates each user a unique device ID cookie that persists across time / login state / etc.
 *
 * This identity is intended for audit trail and user support / debugging purposes. This can be
 * considered a functional cookie, as the ability to properly investigate and resolve problems with
 * a site are an inherent part of operating it.
 *
 * It *should not* be used for general analytics and audience tracking without a proper consent
 * mechanism.
 *
 * It *must not* be used for any application logic as the value is protected only against invalid
 * formats, it is not in any way protected against forgery or cloning.
 */
final class DeviceIdentifier
{
    private static ?DeviceIdentifier $instance;

    /**
     * @internal
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    /**
     * Forces the singleton state to a known value for use in testing.
     */
    public static function forceGlobalTestValue(string $id): void
    {
        self::$instance = new DeviceIdentifier(new ArrayCookieWrapperStub(['did' => $id]));
    }

    /**
     * Initialises the instance, loading the current ID from cookies or setting new cookies.
     *
     * You must call this as early as possible in your bootstrapping to ensure that the ID is set
     * before sending any output.
     */
    public static function initAndEnsureCookieSet(bool $ssl_available, bool $is_cli = (PHP_SAPI === 'cli')): void
    {
        if ($is_cli) {
            // Stub the class without setting cookies for use in a CLI environment.
            // Note that as it's not really appropriate to inject the `is_cli` arg to `::get()`, and we can't stub
            // PHP_SAPI for testing, that method will still return `-unset-` if it is called before
            // ::initAndEnsureCookieSet()
            self::forceGlobalTestValue(self::CLI_ID);
        } else {
            self::$instance = new DeviceIdentifier(new CookieWrapper($ssl_available));
        }
        assert(self::$instance instanceof DeviceIdentifier, 'Should have been assigned by above or by ::forceGlobalTestValue');
        self::$instance->init();
    }

    /**
     * Returns the current device ID.
     *
     * If ::initAndEnsureCookieSet has not yet been called, this method will return the value from
     * $_COOKIE if one was set on a previous request. If this is a user's first request then the
     * value will be `-unset-`
     */
    public static function get(): string
    {
        $i = self::$instance ?? new DeviceIdentifier(new CookieWrapper());

        return $i->getValue();
    }

    public const string CLI_ID = 'cli-------------------';
    public const string COOKIE_LIFETIME = 'P5Y';
    public const string VALID_REGEX = '/^[a-zA-Z0-9\-_=]{22}$/';

    private ?string $id = null;

    private function __construct(private readonly CookieWrapper $cookies)
    {
    }

    /**
     * @internal
     */
    public function getValue(): string
    {
        if ($this->id === null) {
            $id = $this->cookies->get('did') ?: $this->cookies->get('didf');
            if (empty($id)) {
                $id = '-unset-';
            } elseif (!preg_match(self::VALID_REGEX, $id)) {
                // Safety check - prevent malicious user sending long / manipulated ids that could
                // cause excessive log volume or problematic values
                $id = '-invalid-';
            }
            $this->id = $id;
        }

        return $this->id;
    }

    /**
     * @internal
     */
    public function init(): void
    {
        $current_value = $this->getValue();

        if ($current_value === '-unset-' || $current_value === '-invalid-') {
            // Do the init
            $this->assignNewIdAndSetCookies();
        } elseif ($this->cookies->has('did') && $this->cookies->has('didf')) {
            // Delete the spare cookie
            $this->cookies->delete('didf');
        }
    }

    private function assignNewIdAndSetCookies(): void
    {
        $this->id = Base64Url::encode(random_bytes(16));
        $expire = (new DateTimeImmutable())->add(new DateInterval(self::COOKIE_LIFETIME));

        // The cookies are set with httponly=false to allow access from JS (though generally it is
        // preferred to render it server-side and inject into javascript through data attributes /
        // config, to avoid dependency on the cookie name).

        $this->cookies->set(
            'did',
            $this->id,
            ['expires' => $expire, 'httponly' => false, 'secure' => true, 'samesite' => 'none']
        );
        $this->cookies->set(
            'didf',
            $this->id,
            ['expires' => $expire, 'httponly' => false, 'secure' => true, 'samesite' => null]
        );
    }
}
