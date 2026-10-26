<?php

/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 *
 * @licence   BSD-3-Clause
 */

namespace test\unit\Ingenerator\PHPUtils\DateTime;

use DateTimeImmutable;
use DateTimeZone;
use Ingenerator\PHPUtils\DateTime\DateString;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DateStringTest extends TestCase
{
    public static function provider_generic_format()
    {
        return [
            [null, 'Y-m-d', 'nothing', 'nothing'],
            [new DateTimeImmutable('2017-05-04 20:03:02'), 'Y-m-d', '', '2017-05-04'],
            [new DateTimeImmutable('2017-05-04 20:03:02'), 'D d M H:i', '', 'Thu 04 May 20:03'],
        ];
    }

    #[DataProvider('provider_generic_format')]
    public function testItFormatsDateOrReturnsFallbackString($date, $format, $empty, $expect)
    {
        $this->assertSame($expect, DateString::format($date, $format, $empty));
    }

    public static function provider_ymdhis()
    {
        return [
            [null, 'nothing', 'nothing'],
            [new DateTimeImmutable('2017-02-03 10:02:02'), '', '2017-02-03 10:02:02'],
        ];
    }

    #[DataProvider('provider_ymdhis')]
    public function testItFormatsWithYmdhisOrFallback($date, $fallback, $expect)
    {
        $this->assertSame($expect, DateString::ymdhis($date, $fallback));
    }

    public static function provider_ymd()
    {
        return [
            [null, 'nothing', 'nothing'],
            [new DateTimeImmutable('2017-02-03 10:02:02'), '', '2017-02-03'],
        ];
    }

    #[DataProvider('provider_ymd')]
    public function testItFormatsWithYmdOrFallback($date, $fallback, $expect)
    {
        $this->assertSame($expect, DateString::ymd($date, $fallback));
    }

    public static function provider_isoms()
    {
        $lndn = new DateTimeZone('Europe/London');

        return [
            [null, 'nothing', 'nothing'],
            [new DateTimeImmutable('2020-08-21 12:15:54.643214', $lndn), '', '2020-08-21T12:15:54.643214+01:00'],
        ];
    }

    #[DataProvider('provider_isoms')]
    public function testItFormatsToIso8601WithMilliseconds($date, $fallback, $expect)
    {
        $this->assertSame($expect, DateString::isoMS($date, $fallback));
    }
}
