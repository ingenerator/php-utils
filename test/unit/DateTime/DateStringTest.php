<?php

declare(strict_types=1);

/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 *
 * @licence   BSD-3-Clause
 */

namespace test\unit\Ingenerator\PHPUtils\DateTime;

use DateTimeImmutable;
use DateTimeZone;
use Ingenerator\PHPUtils\DateTime\DateString;
use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DateStringTest extends TestCase
{
    /**
     * @return Iterator<int, array<(DateTimeImmutable | string | null)>>
     */
    public static function provider_generic_format(): Iterator
    {
        yield [null, 'Y-m-d', 'nothing', 'nothing'];
        yield [new DateTimeImmutable('2017-05-04 20:03:02'), 'Y-m-d', '', '2017-05-04'];
        yield [new DateTimeImmutable('2017-05-04 20:03:02'), 'D d M H:i', '', 'Thu 04 May 20:03'];
    }

    #[DataProvider('provider_generic_format')]
    public function testItFormatsDateOrReturnsFallbackString(?DateTimeImmutable $date, string $format, string $empty, string $expect): void
    {
        $this->assertSame($expect, DateString::format($date, $format, $empty));
    }

    /**
     * @return Iterator<int, array<(DateTimeImmutable | string | null)>>
     */
    public static function provider_ymdhis(): Iterator
    {
        yield [null, 'nothing', 'nothing'];
        yield [new DateTimeImmutable('2017-02-03 10:02:02'), '', '2017-02-03 10:02:02'];
    }

    #[DataProvider('provider_ymdhis')]
    public function testItFormatsWithYmdhisOrFallback(?DateTimeImmutable $date, string $fallback, string $expect): void
    {
        $this->assertSame($expect, DateString::ymdhis($date, $fallback));
    }

    /**
     * @return Iterator<int, array<(DateTimeImmutable | string | null)>>
     */
    public static function provider_ymd(): Iterator
    {
        yield [null, 'nothing', 'nothing'];
        yield [new DateTimeImmutable('2017-02-03 10:02:02'), '', '2017-02-03'];
    }

    #[DataProvider('provider_ymd')]
    public function testItFormatsWithYmdOrFallback(?DateTimeImmutable $date, string $fallback, string $expect): void
    {
        $this->assertSame($expect, DateString::ymd($date, $fallback));
    }

    /**
     * @return Iterator<int, array<(DateTimeImmutable | string | null)>>
     */
    public static function provider_isoms(): Iterator
    {
        $lndn = new DateTimeZone('Europe/London');
        yield [null, 'nothing', 'nothing'];
        yield [new DateTimeImmutable('2020-08-21 12:15:54.643214', $lndn), '', '2020-08-21T12:15:54.643214+01:00'];
    }

    #[DataProvider('provider_isoms')]
    public function testItFormatsToIso8601WithMilliseconds(?DateTimeImmutable $date, string $fallback, string $expect): void
    {
        $this->assertSame($expect, DateString::isoMS($date, $fallback));
    }
}
