<?php
/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 * @licence   proprietary
 */

namespace test\unit\Ingenerator\PHPUtils\Object;


use Ingenerator\PHPUtils\Object\ObjectPropertyPopulator;

class ObjectPropertyPopulatorTest extends \PHPUnit_Framework_TestCase
{

    public function test_it_assigns_single_property()
    {
        $class = new TestPopulatingClass;
        ObjectPropertyPopulator::assign($class, 'something_private', 'food');
        $this->assertSame('food', $class->toArray()['something_private']);
    }

    public function test_it_assigns_array_of_properties()
    {
        $class = new TestPopulatingClass;
        ObjectPropertyPopulator::assignHash(
            $class,
            [
                'something_private'   => 'bed',
                'something_protected' => 'gold'
            ]
        );
        $this->assertEquals(
            [
                'something_private'   => 'bed',
                'something_protected' => 'gold',
                'already_init'        => 'Foo'
            ],
            $class->toArray()
        );
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function test_it_throws_if_assigning_unknown_property()
    {
        ObjectPropertyPopulator::assignHash(new TestPopulatingClass, ['junk' => 'rubbish']);
    }

}


class TestPopulatingClass
{
    private $something_private;
    protected $something_protected;
    protected $already_init = 'Foo';

    public function toArray()
    {
        return get_object_vars($this);
    }
}
