<?php

namespace test\unit\Ingenerator\PHPUtils\DateTime;

use DateTimeImmutable;
use Ingenerator\PHPUtils\DateTime\DateIntervalUtils;
use PHPUnit\Framework\TestCase;

class DateIntervalUtilsTest extends TestCase
{

    /**
     * @testWith ["P5M", "5 months"]
     *           ["P1Y", "1 year"]
     *           ["P10Y", "10 years"]
     *           ["P3W", "21 days", "NOTE: Weeks is always compiled-out to days in the object, cannot get back to it."]
     *           ["P3W2D", "23 days", "NOTE: Weeks is always compiled-out to days in the object, cannot get back to it."]
     *           ["P1Y3M", "1 year and 3 months"]
     *           ["P2Y3M2D", "2 years, 3 months and 2 days"]
     *           ["PT4H", "4 hours"]
     *           ["P3DT4H", "3 days and 4 hours"]
     *           ["PT5M4S", "5 minutes and 4 seconds"]
     */
    public function test_it_can_parse_to_human_string(string $interval_string, string $expect): void
    {
        $this->assertSame(
            $expect,
            DateIntervalUtils::toHuman(new \DateInterval($interval_string))
        );
    }

    public function provider_unsupported_human_intervals()
    {
        $diff = fn(string $dt1, string $dt2) => (new DateTimeImmutable($dt1))->diff(new DateTimeImmutable($dt2));

        return [
            'with micros e.g. from a diff' => [$diff('now', 'now')],
            'negative'                     => [$diff('2022-01-01 10:03:03', '2021-03-02 10:02:03')],
        ];
    }

    /**
     * @dataProvider provider_unsupported_human_intervals
     */
    public function test_to_human_throws_with_unsupported_intervals(\DateInterval $interval)
    {
        $this->expectException(\InvalidArgumentException::class);
        DateIntervalUtils::toHuman($interval);
    }

}
