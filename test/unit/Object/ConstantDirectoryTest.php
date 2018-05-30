<?php
/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 * @licence   proprietary
 */

namespace test\unit\Ingenerator\PHPUtils\Object;

use Ingenerator\PHPUtils\Object\ConstantDirectory;
use PHPUnit\Framework\TestCase;

/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 * @licence   proprietary
 */
class ConstantDirectoryTest extends TestCase
{

    const TEST_ONE = 'any';
    const TEST_TWO = 'two';
    const TESTTHREE = 'three';
    const ANYTHING = 'another';

    public function test_it_is_initialisable()
    {
        $this->assertInstanceOf(ConstantDirectory::class, $this->newSubject());
    }

    public function test_it_returns_empty_array_with_no_constants()
    {
        $this->assertSame(
            [],
            $this->newSubject(NoConstantsClass::class)->listConstants()
        );
    }

    public function test_it_returns_list_of_all_constants()
    {
        $this->assertSame(
            [
                'TEST_ONE'  => 'any',
                'TEST_TWO'  => 'two',
                'TESTTHREE' => 'three',
                'ANYTHING'  => 'another',
            ],
            $this->newSubject(self::class)->listConstants()
        );

    }

    public function test_it_returns_list_of_constants_with_prefix()
    {
        $this->assertSame(
            [
                'TEST_ONE' => 'any',
                'TEST_TWO' => 'two',
            ],
            $this->newSubject(self::class)->filterConstants('TEST_')
        );
    }


    protected function newSubject($class = self::class)
    {
        return ConstantDirectory::forClass($class);
    }

}

class NoConstantsClass
{
}

