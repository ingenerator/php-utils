<?php

/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 *
 * @licence   BSD-3-Clause
 */

namespace test\unit\Ingenerator\PHPUtils\Validation;

use Ingenerator\PHPUtils\Validation\ValidNumber;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class ValidNumberTest extends TestCase
{
    #[TestWith(['', 13, false])]
    #[TestWith(['junk', 13, false])]
    #[TestWith([0, 13, false])]
    #[TestWith([12, 13, false])]
    #[TestWith([13, 13, true])]
    #[TestWith([18, 13, true])]
    #[TestWith([12.34, 13, false])]
    #[TestWith([56.78, 13.31, true])]
    #[TestWith([13, 13.5, false])]
    #[TestWith([13.5, 13.5, true])]
    #[TestWith([14, 13.5, true])]
    public function testItValidatesMinimumAsGreaterOrEqual($value, $min, $expect)
    {
        $this->assertSame($expect, ValidNumber::minimum($value, $min));
    }

    public function testItThrowsForUnknownRuleName()
    {
        $this->expectException(InvalidArgumentException::class);
        ValidNumber::rule('random nonsense');
    }

    public function testItReturnsValidatorName()
    {
        $this->assertSame(
            'Ingenerator\PHPUtils\Validation\ValidNumber::minimum',
            ValidNumber::rule('minimum')
        );
    }
}
