<?php


namespace test\unit\Ingenerator\PHPUtils\Cookie;


use Ingenerator\PHPUtils\Cookie\ArrayCookieWrapperStub;
use PHPUnit\Framework\TestCase;

class ArrayCookieWrapperStubTest extends TestCase
{
    /**
     * @testWith ["my", true, "cookie"]
     *           ["has", true, "values"]
     *           ["anything", false, null]
     */
    public function test_it_has_cookies_from_constructor_ignoring_superglobal(
        $name,
        $expect_has,
        $expect_val
    ) {
        $old_cookie = $_COOKIE;
        try {
            $_COOKIE = ['anything' => 'whoops'];
            $subject = $this->newSubject(['my' => 'cookie', 'has' => 'values']);
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
        } finally {
            $_COOKIE = $old_cookie;
        }
    }

    public function test_it_can_simulate_set_cookie_and_capture_args()
    {
        $exp     = new \DateTimeImmutable('tomorrow');
        $subject = $this->newSubject();
        $subject->set('any', 'thing', ['expires' => $exp]);
        $this->assertSame(
            [
                'has'   => TRUE,
                'value' => 'thing',
            ],
            [
                'has'   => $subject->has('any'),
                'value' => $subject->get('any'),
            ]
        );
        $this->assertSame(
            ['any' => [['value' => 'thing', 'opts' => ['expires' => $exp]]]],
            $subject->inspectSetCookies()
        );
    }

    protected function newSubject(array $cookies = [])
    {
        return new ArrayCookieWrapperStub($cookies);
    }

}
