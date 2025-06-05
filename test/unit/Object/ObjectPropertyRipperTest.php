<?php
/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 * @licence   BSD-3-Clause
 */

namespace test\unit\Ingenerator\PHPUtils\Object;


use Ingenerator\PHPUtils\Object\ObjectPropertyRipper;
use PHPUnit\Framework\TestCase;
use stdClass;

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

    public function test_it_can_rip_from_stdclass()
    {
        $c = new stdClass();
        $c->data = 'something';
        $c->other = 1;

        $this->assertSame(
            [
                'data' => 'something',
                'other' => 1,
            ],
            ObjectPropertyRipper::ripAll($c),
        );

        $this->assertSame('something', ObjectPropertyRipper::ripOne($c, 'data'));
        $this->assertSame(['other' => 1], ObjectPropertyRipper::rip($c, ['other']));
    }

    public function test_it_can_rip_from_class_that_inherits_from_stdclass()
    {
        // This is an edge case and should be fairly unlikely IRL, but it is valid.

        $c = new class extends stdClass {
            private string $hidden = 'whatever';
        };
        $c->data = 'something';
        $c->other = 1;

        $this->assertSame(
            [
                'hidden' => 'whatever',
                'data' => 'something',
                'other' => 1,
            ],
            ObjectPropertyRipper::ripAll($c),
        );

        $this->assertSame('whatever', ObjectPropertyRipper::ripOne($c, 'hidden'));
        $this->assertSame(['hidden' => 'whatever', 'other' => 1], ObjectPropertyRipper::rip($c, ['hidden', 'other']));
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
