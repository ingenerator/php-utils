<?php
/**
 * @author    Craig Gosman <craig@ingenerator.com>
 * @licence   BSD-3-Clause
 */

namespace test\unit\Ingenerator\PHPUtils\unit\DateTime;

use DateTimeImmutable;
use DateTimeInterface;
use Ingenerator\PHPUtils\DateTime\DateTimeDiff;
use PHPUnit\Framework\TestCase;

class DateTimeDiffTest extends TestCase
{
    public function date_time_diff()
    {
        return [
            [
                new DateTimeImmutable("2017-06-24 10:11:12.456"),
                new DateTimeImmutable("2017-06-24 10:11:12.456"),
                0
            ],
            [
                new DateTimeImmutable("2018-10-01 02:03:04"),
                new DateTimeImmutable("2018-10-03 15:20:07"),
                220_623_000_000
            ],
            [
                new DateTimeImmutable("2018-10-01 02:03:07"),
                new DateTimeImmutable("2018-10-01 02:03:04"),
                -3000_000
            ],
            [
                new DateTimeImmutable("2019-03-02 11:56:10.1230"),
                new DateTimeImmutable("2019-03-02 11:56:10.1280"),
                5_000
            ],
            [
                new DateTimeImmutable("2019-03-02 11:56:07.109999"),
                new DateTimeImmutable("2019-03-02 11:56:10.110000"),
                3_000_001
            ],
            [
                new DateTimeImmutable("2019-03-02 11:56:07.600001"),
                new DateTimeImmutable("2019-03-02 11:56:10.100003"),
                2_500_002
            ],
            [
                new DateTimeImmutable("2020-05-31 19:20:45.123456"),
                new DateTimeImmutable("2020-05-31 19:20:45.123720"),
                264
            ],
            [
                new DateTimeImmutable("2021-02-04 11:43:44.123456"),
                new DateTimeImmutable("2021-02-04 11:43:44.123457"),
                1
            ],
        ];
    }

    /**
     * @dataProvider date_time_diff
     */
    public function test_it_calculates_difference_in_milliseconds(
        DateTimeInterface $date1,
        DateTimeInterface $date2,
        $expected_result
    ) {
        $this->assertEquals($expected_result, DateTimeDiff::microsBetween($date1, $date2));
    }
}
