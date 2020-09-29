<?php
/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 * @licence   proprietary
 */

namespace test\unit\Ingenerator\PHPUtils\Validation;


use Ingenerator\PHPUtils\Validation\ValidNumber;
use PHPUnit\Framework\TestCase;

class ValidNumberTest extends TestCase
{

    /**
     * @testWith ["", false]
     *           ["junk", false]
     *           ["0", false]
     *           ["12", false]
     *           ["13", true]
     *           [18, true]
     */
    public function test_it_validates_minimum_as_greater_or_equal($value, $expect)
    {
        $this->assertSame($expect, ValidNumber::minimum($value, 13));
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
