<?php
/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 * @licence   proprietary
 */

namespace test\unit\Ingenerator\PHPUtils\DateTime;


use Ingenerator\PHPUtils\DateTime\DateString;

class DateStringTest extends \PHPUnit_Framework_TestCase
{
    public function provider_generic_format()
    {
        return [
            [NULL, 'Y-m-d', 'nothing', 'nothing'],
            [new \DateTimeImmutable('2017-05-04 20:03:02'), 'Y-m-d', '', '2017-05-04'],
            [new \DateTimeImmutable('2017-05-04 20:03:02'), 'D d M H:i', '', 'Thu 04 May 20:03'],
        ];
    }

    /**
     * @dataProvider provider_generic_format
     */
    public function test_it_formats_date_or_returns_fallback_string($date, $format, $empty, $expect)
    {
        $this->assertSame($expect, DateString::format($date, $format, $empty));
    }

    public function provider_ymdhis()
    {
        return [
            [NULL, 'nothing', 'nothing'],
            [new \DateTimeImmutable('2017-02-03 10:02:02'), '', '2017-02-03 10:02:02']
        ];
    }

    /**
     * @dataProvider  provider_ymdhis
     */
    public function test_it_formats_with_ymdhis_or_fallback($date, $fallback, $expect)
    {
        $this->assertSame($expect, DateString::ymdhis($date, $fallback));
    }

    public function provider_ymd()
    {
        return [
            [NULL, 'nothing', 'nothing'],
            [new \DateTimeImmutable('2017-02-03 10:02:02'), '', '2017-02-03']
        ];
    }

    /**
     * @dataProvider  provider_ymd
     */
    public function test_it_formats_with_ymd_or_fallback($date, $fallback, $expect)
    {
        $this->assertSame($expect, DateString::ymd($date, $fallback));
    }

}
