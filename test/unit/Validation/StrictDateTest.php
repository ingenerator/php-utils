<?php
/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 * @licence   BSD-3-Clause
 */

namespace test\unit\Ingenerator\PHPUtils\Validation;


use Ingenerator\PHPUtils\DateTime\InvalidUserDateTime;
use Ingenerator\PHPUtils\Validation\StrictDate;
use PHPUnit\Framework\TestCase;

class StrictDateTest extends TestCase
{

    /**
     * @testWith ["", false]
     *           ["junk", false]
     *           ["01/10/01", false]
     *           ["2016-01-01", false]
     *           ["2016-01-01 10:00", false]
     *           ["2016-02-30 10:00:00", false]
     *           ["2016-01-01 50:12:00", false]
     *           ["2016-01-01T10:00:00", false]
     *           ["2016-01-01 10:00:00", true]
     */
    public function test_it_validates_iso_datetime($value, $expect)
    {
        $this->assertSame($expect, StrictDate::iso_datetime($value));
    }

    /**
     * @testWith ["", false]
     *           ["junk", false]
     *           ["01/10/01", false]
     *           ["2016-01-45", false]
     *           ["2016-02-30", false]
     *           ["2016-01-01", true]
     *           ["2016-02-30 10:00:00", false]
     */
    public function test_it_validates_iso_date($value, $expect)
    {
        $this->assertSame($expect, StrictDate::iso_date($value));
    }

    public function provider_datetime_immutable()
    {
        return [
            [NULL, TRUE],
            [new \DateTimeImmutable, TRUE],
            [FALSE, FALSE],
            [new \DateTime, FALSE],
            ["2017-08-01", FALSE],
            [new InvalidUserDateTime('1/30/2018'), FALSE],
        ];
    }

    /**
     * @dataProvider  provider_datetime_immutable
     */
    public function test_it_validates_datetime_immutable_instance($value, $expect)
    {
        $this->assertSame($expect, StrictDate::date_immutable($value));
    }

    /**
     * @dataProvider  provider_datetime_immutable
     */
    public function test_it_validates_date_immutable_instance($value, $expect)
    {
        $this->assertSame($expect, StrictDate::date_immutable($value));
    }

    public function provider_date_before_after_invalid_inputs()
    {
        return [
            [['from' => NULL, 'to' => NULL], 'from', 'to', TRUE],
            [['from' => new \DateTimeImmutable, 'to' => NULL], 'from', 'to', TRUE],
            [['from' => NULL, 'to' => new \DateTimeImmutable], 'from', 'to', TRUE],
            [
                ['from' => new \DateTimeImmutable, 'to' => new InvalidUserDateTime('any')],
                'from',
                'to',
                TRUE
            ],
            [
                ['from' => new InvalidUserDateTime('any'), 'to' => new \DateTimeImmutable],
                'from',
                'to',
                TRUE
            ],
        ];
    }

    /**
     * @dataProvider provider_date_before_after_invalid_inputs
     */
    public function test_date_compare_funcs_validate_invalid_input($data, $from_field, $to_field)
    {
        // This is so that an invalid date just says "invalid date" rather than also "must be after"
        $data = new \ArrayObject($data);
        $this->assertTrue(StrictDate::date_after($data, $from_field, $to_field), 'date_after');
        $this->assertTrue(
            StrictDate::date_on_or_after($data, $from_field, $to_field),
            'date_on_or_after'
        );
    }

    public function provider_date_after_date()
    {
        return [
            // Invalid inputs all true as they should be caught by other rules
            // Simple > the first one
            [
                ['a' => new \DateTimeImmutable, 'b' => new \DateTimeImmutable('-5 mins')],
                'a',
                'b',
                FALSE
            ],
            [
                ['a' => new \DateTimeImmutable('-5 mins'), 'b' => new \DateTimeImmutable],
                'a',
                'b',
                TRUE
            ],
        ];
    }

    /**
     * @testWith ["", "-5 mins", false]
     *           ["-5 mins", "", true]
     */
    public function test_it_validates_date_after_date($from, $to, $expect)
    {
        $this->assertSame(
            $expect,
            StrictDate::date_after(
                new \ArrayObject(
                    [
                        'from' => new \DateTimeImmutable($from),
                        'to'   => new \DateTimeImmutable($to)
                    ]
                ),
                'from',
                'to'
            )
        );
    }

    /**
     * @testWith ["2017-01-05 00:00:00", "2017-01-04 23:59:59", false]
     *           ["2017-01-04 10:00:00", "2017-01-04 23:59:59", true]
     *           ["2017-01-04 00:00:00", "2017-01-04 00:00:00", true]
     *           ["2017-01-04 10:00:00", "2017-01-04 08:00:00", true]
     *           ["2017-05-06 10:00:00", "2018-12-30 00:00:00", true]
     *
     */
    public function test_it_validates_date_on_or_after_date($from, $to, $expect)
    {
        $this->assertSame(
            $expect,
            StrictDate::date_on_or_after(
                new \ArrayObject(
                    [
                        'from' => new \DateTimeImmutable($from),
                        'to'   => new \DateTimeImmutable($to)
                    ]
                ),
                'from',
                'to'
            )
        );
    }

    public function test_it_throws_for_unknown_rule_name()
    {
        $this->expectException(\InvalidArgumentException::class);
        StrictDate::rule('random nonsense');
    }

    public function test_it_returns_validator_name()
    {
        $this->assertSame(
            'Ingenerator\PHPUtils\Validation\StrictDate::iso_date',
            StrictDate::rule('iso_date')
        );
    }

}
