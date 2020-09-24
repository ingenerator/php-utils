<?php
/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 * @licence   proprietary
 */

namespace test\unit\Ingenerator\PHPUtils\DateTime;


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
        $this->assertInstanceOf(\DateTimeImmutable::class, $actual);
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
            DateTimeImmutableFactory::fromUserDateInput($input));
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
        $this->assertInstanceOf(\DateTimeImmutable::class, $actual);
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
        $this->assertInstanceOf(\DateTimeImmutable::class, $actual);
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

}
