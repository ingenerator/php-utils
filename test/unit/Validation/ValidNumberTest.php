<?php
/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 * @licence   BSD-3-Clause
 */

namespace test\unit\Ingenerator\PHPUtils\Validation;


use Ingenerator\PHPUtils\Validation\ValidNumber;
use PHPUnit\Framework\TestCase;

class ValidNumberTest extends TestCase
{

    /**
     * @testWith ["", 13, false]
     *           ["junk", 13, false]
     *           ["0", 13, false]
     *           ["12", 13, false]
     *           ["13", 13, true]
     *           [18, 13, true]
     *           [12.34, 13, false]
     *           [56.78, 13.31, true]
     *           [13, 13.5, false]
     *           [13.5, 13.5, true]
     *           [14, 13.5, true]
     */
    public function test_it_validates_minimum_as_greater_or_equal($value, $min, $expect)
    {
        $this->assertSame($expect, ValidNumber::minimum($value, $min));
    }

    public function test_it_throws_for_unknown_rule_name()
    {
        $this->expectException(\InvalidArgumentException::class);
        ValidNumber::rule('random nonsense');
    }

    public function test_it_returns_validator_name()
    {
        $this->assertSame(
            'Ingenerator\PHPUtils\Validation\ValidNumber::minimum',
            ValidNumber::rule('minimum')
        );
    }

}
