<?php

declare(strict_types=1);

namespace test\unit\Ingenerator\PHPUtils\DateTime;

use DateInterval;
use DateTimeImmutable;
use Ingenerator\PHPUtils\DateTime\DateIntervalUtils;
use InvalidArgumentException;
use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class DateIntervalUtilsTest extends TestCase
{
    #[TestWith(['P5M', '5 months'])]
    #[TestWith(['P1Y', '1 year'])]
    #[TestWith(['P10Y', '10 years'])]
    #[TestWith(['P3W', '21 days', 'NOTE: Weeks is always compiled-out to days in the object, cannot get back to it.'])]
    #[TestWith(['P3W2D', '23 days', 'NOTE: Weeks is always compiled-out to days in the object, cannot get back to it.'])]
    #[TestWith(['P1Y3M', '1 year and 3 months'])]
    #[TestWith(['P2Y3M2D', '2 years, 3 months and 2 days'])]
    #[TestWith(['PT4H', '4 hours'])]
    #[TestWith(['P3DT4H', '3 days and 4 hours'])]
    #[TestWith(['PT5M4S', '5 minutes and 4 seconds'])]
    public function testItCanParseToHumanString(string $interval_string, string $expect): void
    {
        $this->assertSame(
            $expect,
            DateIntervalUtils::toHuman(new DateInterval($interval_string))
        );
    }

    public static function provider_unsupported_human_intervals(): Iterator
    {
        $diff = fn (string $dt1, string $dt2) => (new DateTimeImmutable($dt1))->diff(new DateTimeImmutable($dt2));
        yield 'with micros e.g. from a diff' => [$diff('now', 'now')];
        yield 'negative' => [$diff('2022-01-01 10:03:03', '2021-03-02 10:02:03')];
    }

    #[DataProvider('provider_unsupported_human_intervals')]
    public function testToHumanThrowsWithUnsupportedIntervals(DateInterval $interval): void
    {
        $this->expectException(InvalidArgumentException::class);
        DateIntervalUtils::toHuman($interval);
    }
}
