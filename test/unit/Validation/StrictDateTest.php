<?php
/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 * @licence   proprietary
 */

namespace test\unit\Ingenerator\PHPUtils\Validation;


use Ingenerator\PHPUtils\DateTime\InvalidUserDateTime;
use Ingenerator\PHPUtils\Validation\StrictDate;

class StrictDateTest extends \PHPUnit_Framework_TestCase
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

    public function provider_date_after_date()
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
     * @dataProvider provider_date_after_date
     */
    public function test_it_validates_date_after_date($data, $from_field, $to_field, $expect)
    {
        $this->assertSame(
            $expect,
            StrictDate::date_after(
                new \ArrayObject($data),
                $from_field,
                $to_field
            )
        );
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function test_it_throws_for_unknown_rule_name()
    {
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
