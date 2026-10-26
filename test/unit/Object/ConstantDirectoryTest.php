<?php

/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 *
 * @licence   BSD-3-Clause
 */

namespace test\unit\Ingenerator\PHPUtils\Object;

use Ingenerator\PHPUtils\Object\ConstantDirectory;
use PHPUnit\Framework\TestCase;

/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 *
 * @licence   BSD-3-Clause
 */
class ConstantDirectoryTest extends TestCase
{
    public const TEST_ONE = 'any';
    public const TEST_TWO = 'two';
    public const TESTTHREE = 'three';
    public const ANYTHING = 'another';

    public function testItIsInitialisable(): void
    {
        $this->assertInstanceOf(ConstantDirectory::class, $this->newSubject());
    }

    public function testItReturnsEmptyArrayWithNoConstants(): void
    {
        $this->assertSame(
            [],
            $this->newSubject(NoConstantsClass::class)->listConstants()
        );
    }

    public function testItReturnsListOfAllConstants(): void
    {
        $this->assertSame(
            [
                'TEST_ONE' => 'any',
                'TEST_TWO' => 'two',
                'TESTTHREE' => 'three',
                'ANYTHING' => 'another',
            ],
            $this->newSubject(self::class)->listConstants()
        );
    }

    public function testItReturnsListOfConstantsWithPrefix(): void
    {
        $this->assertSame(
            [
                'TEST_ONE' => 'any',
                'TEST_TWO' => 'two',
            ],
            $this->newSubject(self::class)->filterConstants('TEST_')
        );
    }

    protected function newSubject($class = self::class): ConstantDirectory
    {
        return ConstantDirectory::forClass($class);
    }
}

class NoConstantsClass
{
}
