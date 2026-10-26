<?php

/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 *
 * @licence   BSD-3-Clause
 */

namespace test\unit\Ingenerator\PHPUtils\Validation;

use ArrayObject;
use DateTime;
use DateTimeImmutable;
use Ingenerator\PHPUtils\Validation\StrictDate;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class StrictDateTest extends TestCase
{
    #[TestWith(['', false])]
    #[TestWith([null, false])]
    #[TestWith(['junk', false])]
    #[TestWith(['01/10/01', false])]
    #[TestWith(['2016-01-01', false])]
    #[TestWith(['2016-01-01 10:00', false])]
    #[TestWith(['2016-02-30 10:00:00', false])]
    #[TestWith(['2016-01-01 50:12:00', false])]
    #[TestWith(['2016-01-01T10:00:00', false])]
    #[TestWith(['2016-01-01 10:00:00', true])]
    public function testItValidatesIsoDatetime($value, $expect)
    {
        $this->assertSame($expect, StrictDate::iso_datetime($value));
    }

    #[TestWith(['', false])]
    #[TestWith([null, false])]
    #[TestWith(['junk', false])]
    #[TestWith(['01/10/01', false])]
    #[TestWith(['2016-01-45', false])]
    #[TestWith(['2016-02-30', false])]
    #[TestWith(['2016-01-01', true])]
    #[TestWith(['2016-02-30 10:00:00', false])]
    public function testItValidatesIsoDate($value, $expect)
    {
        $this->assertSame($expect, StrictDate::iso_date($value));
    }

    public static function provider_datetime_immutable()
    {
        return [
            [null, true],
            [new DateTimeImmutable(), true],
            [false, false],
            [new DateTime(), false],
            ['2017-08-01', false],
        ];
    }

    #[DataProvider('provider_datetime_immutable')]
    public function testItValidatesDatetimeImmutableInstance($value, $expect)
    {
        $this->assertSame($expect, StrictDate::date_immutable($value));
    }

    #[DataProvider('provider_datetime_immutable')]
    public function testItValidatesDateImmutableInstance($value, $expect)
    {
        $this->assertSame($expect, StrictDate::date_immutable($value));
    }

    public static function provider_date_before_after_invalid_inputs()
    {
        return [
            [['from' => null, 'to' => null], 'from', 'to', true],
            [['from' => new DateTimeImmutable(), 'to' => null], 'from', 'to', true],
            [['from' => null, 'to' => new DateTimeImmutable()], 'from', 'to', true],
        ];
    }

    #[DataProvider('provider_date_before_after_invalid_inputs')]
    public function testDateCompareFuncsValidateInvalidInput($data, $from_field, $to_field)
    {
        // This is so that an invalid date just says "invalid date" rather than also "must be after"
        $data = new ArrayObject($data);
        $this->assertTrue(StrictDate::date_after($data, $from_field, $to_field), 'date_after');
        $this->assertTrue(
            StrictDate::date_on_or_after($data, $from_field, $to_field),
            'date_on_or_after'
        );
    }

    public static function provider_date_after_date()
    {
        return [
            'long before' => ['2022-10-02 13:04:03', '2015-11-02 10:03:02', false],
            'just before' => ['2022-10-02 13:01:03.999999', '2022-10-02 13:01:03.999998', false],
            'exact moment' => ['2022-10-02 13:01:03.999999', '2022-10-02 13:01:03.999999', false],
            'just after' => ['2022-10-02 13:01:03.999999', '2022-10-02 13:01:04.000000', true],
            'seconds after' => ['2022-10-02 13:01:03', '2022-10-02 13:01:06.000000', true],
            'long after' => ['2022-10-02 13:01:03', '2023-03-05 18:01:06.000000', true],
        ];
    }

    #[DataProvider('provider_date_after_date')]
    public function testItValidatesDateAfterDate($from, $to, $expect)
    {
        $this->assertSame(
            $expect,
            StrictDate::date_after(
                new ArrayObject(
                    [
                        'from' => new DateTimeImmutable($from),
                        'to' => new DateTimeImmutable($to),
                    ]
                ),
                'from',
                'to'
            )
        );
    }

    #[TestWith(['2017-01-05 00:00:00', '2017-01-04 23:59:59', false])]
    #[TestWith(['2017-01-04 10:00:00', '2017-01-04 23:59:59', true])]
    #[TestWith(['2017-01-04 00:00:00', '2017-01-04 00:00:00', true])]
    #[TestWith(['2017-01-04 10:00:00', '2017-01-04 08:00:00', true])]
    #[TestWith(['2017-05-06 10:00:00', '2018-12-30 00:00:00', true])]
    public function testItValidatesDateOnOrAfterDate($from, $to, $expect)
    {
        $this->assertSame(
            $expect,
            StrictDate::date_on_or_after(
                new ArrayObject(
                    [
                        'from' => new DateTimeImmutable($from),
                        'to' => new DateTimeImmutable($to),
                    ]
                ),
                'from',
                'to'
            )
        );
    }

    public function testItThrowsForUnknownRuleName()
    {
        $this->expectException(InvalidArgumentException::class);
        StrictDate::rule('random nonsense');
    }

    public function testItReturnsValidatorName()
    {
        $this->assertSame(
            'Ingenerator\PHPUtils\Validation\StrictDate::iso_date',
            StrictDate::rule('iso_date')
        );
    }
}
