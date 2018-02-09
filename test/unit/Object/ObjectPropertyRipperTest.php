<?php
/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 * @licence   proprietary
 */

namespace test\unit\Ingenerator\PHPUtils\Object;


use Ingenerator\PHPUtils\Object\ObjectPropertyRipper;

class ObjectPropertyRipperTest extends \PHPUnit_Framework_TestCase
{

    public function test_it_rips_properties()
    {
        $this->assertEquals(
            ['something_private' => 'precious', 'something_protected' => 'water'],
            ObjectPropertyRipper::rip(
                new TestRippingClass,
                ['something_private', 'something_protected']
            )
        );
    }

    public function test_it_rips_one_property()
    {
        $this->assertEquals(
            'precious',
            ObjectPropertyRipper::ripOne(new TestRippingClass, 'something_private')
        );
    }
}

class TestRippingClass
{
    private $something_private = 'precious';
    protected $something_protected = 'water';
    protected $something_else = 'else';
}

