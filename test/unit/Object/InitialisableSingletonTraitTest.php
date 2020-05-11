<?php

namespace test\unit\Ingenerator\PHPUtils\Object;

use Ingenerator\PHPUtils\Object\InitialisableSingletonTrait;
use Ingenerator\PHPUtils\Object\SingletonNotInitialisedException;
use PHPUnit\Framework\TestCase;

class InitialisableSingletonTraitTest extends TestCase
{

    public function test_it_throws_if_not_initialised()
    {
        $class = new class {
            use InitialisableSingletonTrait;
        };
        $this->expectException(SingletonNotInitialisedException::class);
        $class::instance();
    }

    public function test_it_throws_on_attempt_to_reinitialise()
    {
        $class = new class {
            use InitialisableSingletonTrait;
        };
        $class::initialise(function () use ($class) { return new $class; });
        $this->expectException(\LogicException::class);
        $class::initialise(function () use ($class) { return new $class; });
    }

    public function test_it_indicates_if_initialised_or_not()
    {
        $class = new class {
            use InitialisableSingletonTrait;
        };
        $this->assertSame(FALSE, $class::isInitialised(), 'Not initialised before init');

        $class::initialise(function () use ($class) { return new $class; });
        $this->assertSame(TRUE, $class::isInitialised(), 'Initialised after init');
    }


    public function test_it_can_initialise_and_returns_singleton_from_then_on()
    {
        $class = new class {
            use InitialisableSingletonTrait;
        };
        $class::initialise(function () use ($class) { return new $class; });
        $this->assertInstanceOf(\get_class($class), $class::instance());
        $this->assertSame($class::instance(), $class::instance());
    }

    public function test_it_throws_if_initialiser_does_not_return_instance_of_class()
    {
        $class = new class {
            use InitialisableSingletonTrait;
        };
        $this->expectException(\InvalidArgumentException::class);
        $class::initialise(function () use ($class) { return new \stdClass; });
    }

    public function test_it_throws_if_initialiser_returns_nothing()
    {
        $class = new class {
            use InitialisableSingletonTrait;
        };
        $this->expectException(\InvalidArgumentException::class);
        $class::initialise(function () use ($class) { });
    }

    public function test_it_is_not_affected_by_multiple_classes_using_the_trait()
    {
        CustomSingletonOne::initialise(function () { return new CustomSingletonOne; });
        $this->assertSame(
            ['one' => TRUE, 'two' => FALSE],
            ['one' => CustomSingletonOne::isInitialised(), 'two' => CustomSingletonTwo::isInitialised()],
            'Expect correct initial inits'
        );

        $inst1 = CustomSingletonOne::instance();
        CustomSingletonTwo::initialise(function () { return new CustomSingletonTwo; });
        $this->assertSame(
            ['one' => TRUE, 'two' => TRUE,],
            ['one' => CustomSingletonOne::isInitialised(), 'two' => CustomSingletonTwo::isInitialised()],
            'Expect correct inits after init both'
        );

        $this->assertSame($inst1, CustomSingletonOne::instance(), 'Should still have inst1');
        $this->assertSame(
            CustomSingletonTwo::instance(),
            CustomSingletonTwo::instance(),
            'Should have inst2 singleton'
        );
    }

    public function test_it_can_be_extended()
    {
        $child = new class extends CustomParentSingleton {
        };

        $child::initialise(
            function () use ($child) {
                return new $child;
            }
        );

        $this->assertSame(
            [
                'child'  => TRUE,
                'parent' => TRUE,
            ],
            [
                'child'  => $child::isInitialised(),
                'parent' => CustomParentSingleton::isInitialised(),
            ],
            'Initialised after init'
        );
        $this->assertSame($child::instance(), $child::instance(), 'Works as singleton');
        $this->assertSame(
            $child::instance(),
            CustomParentSingleton::instance(),
            'Parent and child singleton are same instance'
        );
        $this->assertInstanceOf(\get_class($child), $child::instance(), 'Singleton is of the child class');
    }

}


class CustomSingletonOne
{
    use InitialisableSingletonTrait;
}

class CustomSingletonTwo
{
    use InitialisableSingletonTrait;
}

class CustomParentSingleton
{
    use InitialisableSingletonTrait;
}
