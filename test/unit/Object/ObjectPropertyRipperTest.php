<?php
/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 * @licence   BSD-3-Clause
 */

namespace test\unit\Ingenerator\PHPUtils\Object;


use Ingenerator\PHPUtils\Object\ObjectPropertyRipper;
use PHPUnit\Framework\TestCase;

class ObjectPropertyRipperTest extends TestCase
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

    public function test_it_rips_all_properties()
    {
        $this->assertEquals(
            [
                'something_private'   => 'precious',
                'something_protected' => 'water',
                'something_else'      => 'else',
            ],
            ObjectPropertyRipper::ripAll(new TestRippingClass)
        );
    }

    public function test_it_throws_if_ripping_all_from_an_object_with_private_parent_properties()
    {
        $this->expectException(\DomainException::class);
        ObjectPropertyRipper::ripAll(new ExtensionRippingClass);
    }

}

class TestRippingClass
{
    private   $something_private   = 'precious';
    protected $something_protected = 'water';
    protected $something_else      = 'else';
}

class ExtensionRippingClass extends TestRippingClass
{
    private $child_private = 'other-private';
}
