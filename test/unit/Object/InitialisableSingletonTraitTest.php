<?php

declare(strict_types=1);

namespace test\unit\Ingenerator\PHPUtils\Object;

use Ingenerator\PHPUtils\Object\InitialisableSingletonTrait;
use Ingenerator\PHPUtils\Object\SingletonNotInitialisedException;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use stdClass;

final class InitialisableSingletonTraitTest extends TestCase
{
    public function testItThrowsIfNotInitialised(): void
    {
        $class = new class {
            use InitialisableSingletonTrait;
        };
        $this->expectException(SingletonNotInitialisedException::class);
        $class::instance();
    }

    public function testItThrowsOnAttemptToReinitialise(): void
    {
        $class = new class {
            use InitialisableSingletonTrait;
        };
        $class::initialise(fn (): object => new $class());
        $this->expectException(LogicException::class);
        $class::initialise(fn (): object => new $class());
    }

    public function testItIndicatesIfInitialisedOrNot(): void
    {
        $class = new class {
            use InitialisableSingletonTrait;
        };
        $this->assertFalse($class::isInitialised(), 'Not initialised before init');

        $class::initialise(fn (): object => new $class());
        $this->assertTrue($class::isInitialised(), 'Initialised after init');
    }

    public function testItCanInitialiseAndReturnsSingletonFromThenOn(): void
    {
        $class = new class {
            use InitialisableSingletonTrait;
        };
        $class::initialise(fn (): object => new $class());
        $this->assertInstanceOf($class::class, $class::instance());
        $this->assertSame($class::instance(), $class::instance());
    }

    public function testItThrowsIfInitialiserDoesNotReturnInstanceOfClass(): void
    {
        $class = new class {
            use InitialisableSingletonTrait;
        };
        $this->expectException(InvalidArgumentException::class);
        $class::initialise(fn (): stdClass => new stdClass());
    }

    public function testItThrowsIfInitialiserReturnsNothing(): void
    {
        $class = new class {
            use InitialisableSingletonTrait;
        };
        $this->expectException(InvalidArgumentException::class);
        $class::initialise(function (): void { });
    }

    public function testItIsNotAffectedByMultipleClassesUsingTheTrait(): void
    {
        CustomSingletonOne::initialise(fn (): CustomSingletonOne => new CustomSingletonOne());
        $this->assertSame(
            ['one' => true, 'two' => false],
            ['one' => CustomSingletonOne::isInitialised(), 'two' => CustomSingletonTwo::isInitialised()],
            'Expect correct initial inits'
        );

        $inst1 = CustomSingletonOne::instance();
        CustomSingletonTwo::initialise(fn (): CustomSingletonTwo => new CustomSingletonTwo());
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

    public function testItCanBeExtended(): void
    {
        $child = new class extends CustomParentSingleton {
        };

        $child::initialise(
            fn (): object => new $child()
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
        $this->assertInstanceOf($child::class, $child::instance(), 'Singleton is of the child class');
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
