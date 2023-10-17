<?php
/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 * @licence   BSD-3-Clause
 */

namespace test\unit\Ingenerator\PHPUtils\DateTime;


use DateTimeImmutable;
use Ingenerator\PHPUtils\DateTime\DateString;
use Ingenerator\PHPUtils\DateTime\DateTimeImmutableFactory;
use Ingenerator\PHPUtils\DateTime\InvalidUserDateTime;
use PHPUnit\Framework\TestCase;

class DateTimeImmutableFactoryTest extends TestCase
{

    /**
     * @testWith ["2017-07-09", "2017-07-09"]
     *           ["09/07/2017", "2017-07-09"]
     *           ["5/9/2017", "2017-09-05"]
     *           ["9/5/15", "2015-05-09"]
     */
    public function test_it_factories_correct_object_from_valid_user_date_input($input, $expect)
    {
        $actual = DateTimeImmutableFactory::fromUserDateInput($input);
        $this->assertInstanceOf(DateTimeImmutable::class, $actual);
        $this->assertNotInstanceOf(InvalidUserDateTime::class, $actual);
        $this->assertEquals($expect.' 00:00:00', $actual->format('Y-m-d H:i:s'));
    }

    /**
     * @testWith [""]
     *           [null]
     */
    public function test_it_factories_null_from_empty_user_date_input($input)
    {
        $this->assertNull(
            DateTimeImmutableFactory::fromUserDateInput($input)
        );
    }

    /**
     * @testWith ["junk"]
     *           ["2017-13-02"]
     *           ["30/02/2015"]
     *           ["02/30/2015"]
     */
    public function test_it_factories_invalid_object_from_invalid_user_date_input($input)
    {
        $actual = DateTimeImmutableFactory::fromUserDateInput($input);
        $this->assertInstanceOf(InvalidUserDateTime::class, $actual);
        $this->assertEquals($input, $actual->format('anything'));
    }

    /**
     * @testWith ["2017-07-09 10:00:00", "2017-07-09 10:00:00"]
     *           ["2017-07-09 10:00", "2017-07-09 10:00:00"]
     *           ["2017-11-30 23:50", "2017-11-30 23:50:00"]
     */
    public function test_it_factories_correct_object_from_valid_user_date_time_input(
        $input,
        $expect
    ) {
        $actual = DateTimeImmutableFactory::fromUserDateTimeInput($input);
        $this->assertInstanceOf(DateTimeImmutable::class, $actual);
        $this->assertNotInstanceOf(InvalidUserDateTime::class, $actual);
        $this->assertEquals($expect, $actual->format('Y-m-d H:i:s'));
    }

    /**
     * @testWith [""]
     *           [null]
     */
    public function test_it_factories_null_from_empty_user_date_time_input($input)
    {
        $this->assertNull(DateTimeImmutableFactory::fromUserDateTimeInput($input));
    }

    /**
     * @testWith ["junk"]
     *           ["2017-11-10 32:02:30"]
     *           ["10/11/2017 02:02:02"]
     */
    public function test_it_factories_invalid_object_from_invalid_user_date_time_input($input)
    {
        $actual = DateTimeImmutableFactory::fromUserDateInput($input);
        $this->assertInstanceOf(InvalidUserDateTime::class, $actual);
        $this->assertEquals($input, $actual->format('anything'));
    }

    /**
     * @testWith ["2017-07-09", "2017-07-09 00:00:00"]
     */
    public function test_it_factories_correct_object_from_valid_ymd_input($input, $expect)
    {
        $actual = DateTimeImmutableFactory::fromYmdInput($input);
        $this->assertInstanceOf(DateTimeImmutable::class, $actual);
        $this->assertNotInstanceOf(InvalidUserDateTime::class, $actual);
        $this->assertEquals($expect, $actual->format('Y-m-d H:i:s'));

    }

    /**
     * @testWith [""]
     *           [null]
     */
    public function test_it_factories_null_from_empty_ymd_input($input)
    {
        $this->assertNull(DateTimeImmutableFactory::fromYmdInput($input));
    }

    /**
     * @testWith ["junk"]
     *           ["2017-11-10 12:02:30"]
     *           ["2017-14-10"]
     *           ["10/11/2017"]
     */
    public function test_it_factories_invalid_object_from_invalid_ymd_input($input)
    {
        $actual = DateTimeImmutableFactory::fromYmdInput($input);
        $this->assertInstanceOf(InvalidUserDateTime::class, $actual);
        $this->assertEquals($input, $actual->format('anything'));
    }

    /**
     * @testWith ["2017-07-09 10:01:02", "2017-07-09T10:01:02.000000+01:00"]
     */
    public function test_it_factories_correct_object_from_valid_ymdhis_in_default_tz($input, $expect)
    {
        $old_default = \date_default_timezone_get();
        try {
            \date_default_timezone_set('Europe/London');
            $actual = DateTimeImmutableFactory::fromYmdHis($input);
            $this->assertInstanceOf(DateTimeImmutable::class, $actual);
            $this->assertSame('Europe/London', $actual->getTimezone()->getName());
            $this->assertSame($expect, $actual->format('Y-m-d\TH:i:s.uP'));
        } finally {
            \date_default_timezone_set($old_default);
        }
    }

    /**
     * @testWith [""]
     *           ["yesterday"]
     *           ["2017-11-10"]
     *           ["2017-11-10 26:10:10"]
     *           ["2017-14-10"]
     *           ["10/11/2017"]
     */
    public function test_it_throws_from_invalid_ymdhis_input($input)
    {
        $this->expectException(\InvalidArgumentException::class);
        DateTimeImmutableFactory::fromYmdHis($input);
    }

    public function test_it_factories_from_unix_timestamp_in_default_tz()
    {
        try {
            $old_default = \date_default_timezone_get();
            \date_default_timezone_set('Europe/London');
            $actual = DateTimeImmutableFactory::atUnixSeconds(1600947830);
            $this->assertSame('Europe/London', $actual->getTimezone()->getName());
            $this->assertSame('2020-09-24T12:43:50+01:00', $actual->format(\DateTime::ATOM));
        } finally {
            \date_default_timezone_set($old_default);
        }
    }

    /**
     * @testWith [1598008554.643, "2020-08-21T12:15:54.643000+01:00"]
     *           ["1598008554.64321424", "2020-08-21T12:15:54.643214+01:00"]
     *           ["1274437650", "2010-05-21T11:27:30.000000+01:00"]
     *           [1274437650, "2010-05-21T11:27:30.000000+01:00"]
     *           [1274437650.0, "2010-05-21T11:27:30.000000+01:00"]
     */
    public function test_it_factories_from_microtime_in_default_tz($val, $expect)
    {
        try {
            $old_default = \date_default_timezone_get();
            \date_default_timezone_set('Europe/London');
            $actual = DateTimeImmutableFactory::atMicrotime($val);
            $this->assertSame('Europe/London', $actual->getTimezone()->getName());
            $this->assertSame($expect, $actual->format('Y-m-d\TH:i:s.uP'));
        } finally {
            \date_default_timezone_set($old_default);
        }
    }

    /**
     * @testWith ["2020-03-04 10:11:12", "Y-m-d H:i:s", "2020-03-04T10:11:12.000000+00:00"]
     *           ["2020-08-21 12:15:54.643214+01:00", "Y-m-d H:i:s.uP", "2020-08-21T12:15:54.643214+01:00"]
     */
    public function test_it_factories_from_strict_date_format(string $val, string $format, string $expect): void
    {
        $actual = DateTimeImmutableFactory::fromStrictFormat($val, $format);
        $this->assertInstanceOf(DateTimeImmutable::class, $actual);
        $this->assertSame($expect, $actual->format('Y-m-d\TH:i:s.uP'));
    }

    /**
     * @testWith ["01-02-2020 10:20:30", "Y-m-d H:i:s"]
     *           ["2021-02-23 15:16:17", "Y-m-d\\TH:i:s.uP"]
     */
    public function test_it_throws_from_strict_date_format(string $val, string $format): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("`$val` is not a valid date/time in the format `$format`");
        DateTimeImmutableFactory::fromStrictFormat($val, $format);
    }

    public function provider_from_iso_ms()
    {
        return [
            '6 millis in +00:00'         => ['2023-02-02T10:03:02.123456+00:00', '2023-02-02T10:03:02.123456+00:00'],
            '6 millis in +01:00'         => ['2024-01-10T12:23:42.456789+01:00', '2024-01-10T12:23:42.456789+01:00'],
            '6 millis in -06:30'         => ['2023-04-30T15:56:15.987654-06:30', '2023-04-30T15:56:15.987654-06:30'],
            '6 millis, Zulu time'        => ['2023-02-02T10:03:02.123456Z', '2023-02-02T10:03:02.123456+00:00'],
            '6 millis, zulu time'        => ['2023-02-02T10:03:02.123456z', '2023-02-02T10:03:02.123456+00:00'],
            'without millis in +01:00'   => ['2023-04-30T15:56:15+01:00', '2023-04-30T15:56:15.000000+01:00'],
            'without millis in zulu'     => ['2023-04-30T15:56:15Z', '2023-04-30T15:56:15.000000+00:00'],
            '3-digit millis in zulu'     => ['2023-04-30T15:56:15.123Z', '2023-04-30T15:56:15.123000+00:00'],
            '9-digit millis in zulu'     => ['2023-04-30T15:56:15.1234561239Z', '2023-04-30T15:56:15.123456+00:00'],
            '8-digit millis numeric tz'  => ['2023-04-30T15:56:15.12345642-03:30', '2023-04-30T15:56:15.123456-03:30'],
            // We don't want to round, because if this represents "now" we don't want to risk getting even a ms into the
            // future. Also, lower-precision clocks don't round time, they wait till the next full tick and roll over
            'extra millis are truncated' => ['2023-04-30T15:56:15.1234566-03:30', '2023-04-30T15:56:15.123456-03:30'],
        ];
    }

    /**
     * @dataProvider provider_from_iso_ms
     */
    public function test_it_factories_from_iso_format(string $input, string $expect)
    {
        $actual = DateTimeImmutableFactory::fromIso($input);
        $this->assertSame($expect, DateString::isoMS($actual));
    }

    public function provider_throws_from_invalid_iso()
    {
        return [
            'missing T separator' => ['2023-04-30 15:56:15.12345642-03:30'],
            'named timezone'      => ['2023-04-30T15:56:15.12345642 Europe/London'],
            'nonsense date'       => ['2023-02-31T15:56:15.12345642+00:00'],
            'nonsense date, zulu' => ['2023-02-31T15:56:15.12345642Z'],
            'nonsense time'       => ['2023-02-28T45:23:59+00:00'],
        ];
    }

    /**
     * @dataProvider provider_throws_from_invalid_iso
     */
    public function test_it_throws_from_invalid_iso_format($input)
    {
        $this->expectExceptionMessage("`$input` cannot be parsed as a valid ISO date-time");
        $this->expectException(\InvalidArgumentException::class);
        DateTimeImmutableFactory::fromIso($input);
    }

    public function test_it_can_factory_with_zero_micros()
    {
        $result = DateTimeImmutableFactory::zeroMicros(
            DateTimeImmutableFactory::fromIso('2023-01-03T10:02:03.123456+01:00')
        );
        $this->assertSame('2023-01-03T10:02:03.000000+01:00', DateString::isoMS($result));
    }

    public function test_zero_micros_uses_current_time_by_default()
    {
        $before = DateTimeImmutableFactory::fromYmdHis(date('Y-m-d H:i:s'));
        $result = DateTimeImmutableFactory::zeroMicros();
        $after  = new DateTimeImmutable();
        $this->assertSame('000000', $result->format('u'), 'Micros are zero');
        $this->assertLessThanOrEqual($after, $result, 'Should be before now');
        $this->assertGreaterThanOrEqual($before, $result, 'Should be after start of test (ignoring micros)');
    }

}
