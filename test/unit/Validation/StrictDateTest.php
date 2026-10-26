<?php

declare(strict_types=1);

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
use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class StrictDateTest extends TestCase
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
    public function testItValidatesIsoDatetime($value, $expect): void
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
    public function testItValidatesIsoDate($value, $expect): void
    {
        $this->assertSame($expect, StrictDate::iso_date($value));
    }

    /**
     * @return Iterator<int, array<(bool | DateTime | DateTimeImmutable | string | null)>>
     */
    public static function provider_datetime_immutable(): Iterator
    {
        yield [null, true];
        yield [new DateTimeImmutable(), true];
        yield [false, false];
        yield [new DateTime(), false];
        yield ['2017-08-01', false];
    }

    #[DataProvider('provider_datetime_immutable')]
    public function testItValidatesDatetimeImmutableInstance(DateTimeImmutable|bool|DateTime|string|null $value, bool $expect): void
    {
        $this->assertSame($expect, StrictDate::date_immutable($value));
    }

    #[DataProvider('provider_datetime_immutable')]
    public function testItValidatesDateImmutableInstance(DateTimeImmutable|bool|DateTime|string|null $value, bool $expect): void
    {
        $this->assertSame($expect, StrictDate::date_immutable($value));
    }

    /**
     * @return Iterator<int, array<int, (array<string, (DateTimeImmutable | null)> | bool | string)>>
     */
    public static function provider_date_before_after_invalid_inputs(): Iterator
    {
        yield [['from' => null, 'to' => null], 'from', 'to', true];
        yield [['from' => new DateTimeImmutable(), 'to' => null], 'from', 'to', true];
        yield [['from' => null, 'to' => new DateTimeImmutable()], 'from', 'to', true];
    }

    #[DataProvider('provider_date_before_after_invalid_inputs')]
    public function testDateCompareFuncsValidateInvalidInput(array $data, string $from_field, string $to_field): void
    {
        // This is so that an invalid date just says "invalid date" rather than also "must be after"
        $data = new ArrayObject($data);
        $this->assertTrue(StrictDate::date_after($data, $from_field, $to_field), 'date_after');
        $this->assertTrue(
            StrictDate::date_on_or_after($data, $from_field, $to_field),
            'date_on_or_after'
        );
    }

    /**
     * @return Iterator<string, array<(bool | string)>>
     */
    public static function provider_date_after_date(): Iterator
    {
        yield 'long before' => ['2022-10-02 13:04:03', '2015-11-02 10:03:02', false];
        yield 'just before' => ['2022-10-02 13:01:03.999999', '2022-10-02 13:01:03.999998', false];
        yield 'exact moment' => ['2022-10-02 13:01:03.999999', '2022-10-02 13:01:03.999999', false];
        yield 'just after' => ['2022-10-02 13:01:03.999999', '2022-10-02 13:01:04.000000', true];
        yield 'seconds after' => ['2022-10-02 13:01:03', '2022-10-02 13:01:06.000000', true];
        yield 'long after' => ['2022-10-02 13:01:03', '2023-03-05 18:01:06.000000', true];
    }

    #[DataProvider('provider_date_after_date')]
    public function testItValidatesDateAfterDate(string $from, string $to, bool $expect): void
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
    public function testItValidatesDateOnOrAfterDate($from, $to, $expect): void
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

    public function testItThrowsForUnknownRuleName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        StrictDate::rule('random nonsense');
    }

    public function testItReturnsValidatorName(): void
    {
        $this->assertSame(
            'Ingenerator\PHPUtils\Validation\StrictDate::iso_date',
            StrictDate::rule('iso_date')
        );
    }
}
