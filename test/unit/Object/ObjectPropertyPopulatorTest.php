<?php

declare(strict_types=1);

/**
 * @author    Andrew Coulton <andrew@ingenerator.com>
 *
 * @licence   BSD-3-Clause
 */

namespace test\unit\Ingenerator\PHPUtils\Object;

use Ingenerator\PHPUtils\Object\ObjectPropertyPopulator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

use function get_object_vars;

final class ObjectPropertyPopulatorTest extends TestCase
{
    public function testItAssignsSingleProperty(): void
    {
        $class = new TestPopulatingClass();
        ObjectPropertyPopulator::assign($class, 'something_private', 'food');
        $this->assertSame('food', $class->toArray()['something_private']);
    }

    public function testItAssignsArrayOfProperties(): void
    {
        $class = new TestPopulatingClass();
        ObjectPropertyPopulator::assignHash(
            $class,
            [
                'something_private' => 'bed',
                'something_protected' => 'gold',
            ]
        );
        $this->assertEquals(
            [
                'something_private' => 'bed',
                'something_protected' => 'gold',
                'already_init' => 'Foo',
            ],
            $class->toArray()
        );
    }

    public function testItThrowsIfAssigningUnknownProperty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ObjectPropertyPopulator::assignHash(new TestPopulatingClass(), ['junk' => 'rubbish']);
    }
}

class TestPopulatingClass
{
    /* @phpstan-ignore property.onlyRead (written by the populator in tests) */
    private mixed $something_private;
    protected mixed $something_protected;
    protected string $already_init = 'Foo';

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
