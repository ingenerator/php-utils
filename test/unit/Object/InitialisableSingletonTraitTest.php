<?php

namespace test\unit\Ingenerator\PHPUtils\Object;

use Ingenerator\PHPUtils\Object\InitialisableSingletonTrait;
use Ingenerator\PHPUtils\Object\SingletonNotInitialisedException;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use stdClass;

use function get_class;

class InitialisableSingletonTraitTest extends TestCase
{
    public function testItThrowsIfNotInitialised()
    {
        $class = new class {
            use InitialisableSingletonTrait;
        };
        $this->expectException(SingletonNotInitialisedException::class);
        $class::instance();
    }

    public function testItThrowsOnAttemptToReinitialise()
    {
        $class = new class {
            use InitialisableSingletonTrait;
        };
        $class::initialise(function () use ($class) { return new $class(); });
        $this->expectException(LogicException::class);
        $class::initialise(function () use ($class) { return new $class(); });
    }

    public function testItIndicatesIfInitialisedOrNot()
    {
        $class = new class {
            use InitialisableSingletonTrait;
        };
        $this->assertSame(false, $class::isInitialised(), 'Not initialised before init');

        $class::initialise(function () use ($class) { return new $class(); });
        $this->assertSame(true, $class::isInitialised(), 'Initialised after init');
    }

    public function testItCanInitialiseAndReturnsSingletonFromThenOn()
    {
        $class = new class {
            use InitialisableSingletonTrait;
        };
        $class::initialise(function () use ($class) { return new $class(); });
        $this->assertInstanceOf(get_class($class), $class::instance());
        $this->assertSame($class::instance(), $class::instance());
    }

    public function testItThrowsIfInitialiserDoesNotReturnInstanceOfClass()
    {
        $class = new class {
            use InitialisableSingletonTrait;
        };
        $this->expectException(InvalidArgumentException::class);
        $class::initialise(function () { return new stdClass(); });
    }

    public function testItThrowsIfInitialiserReturnsNothing()
    {
        $class = new class {
            use InitialisableSingletonTrait;
        };
        $this->expectException(InvalidArgumentException::class);
        $class::initialise(function () { });
    }

    public function testItIsNotAffectedByMultipleClassesUsingTheTrait()
    {
        CustomSingletonOne::initialise(function () { return new CustomSingletonOne(); });
        $this->assertSame(
            ['one' => true, 'two' => false],
            ['one' => CustomSingletonOne::isInitialised(), 'two' => CustomSingletonTwo::isInitialised()],
            'Expect correct initial inits'
        );

        $inst1 = CustomSingletonOne::instance();
        CustomSingletonTwo::initialise(function () { return new CustomSingletonTwo(); });
        $this->assertSame(
            ['one' => true, 'two' => true],
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

    public function testItCanBeExtended()
    {
        $child = new class extends CustomParentSingleton {
        };

        $child::initialise(
            function () use ($child) {
                return new $child();
            }
        );

        $this->assertSame(
            [
                'child' => true,
                'parent' => true,
            ],
            [
                'child' => $child::isInitialised(),
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
        $this->assertInstanceOf(get_class($child), $child::instance(), 'Singleton is of the child class');
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
