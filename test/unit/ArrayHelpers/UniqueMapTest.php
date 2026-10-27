<?php

declare(strict_types=1);

namespace test\unit\Ingenerator\PHPUtils\ArrayHelpers;

use Ingenerator\PHPUtils\ArrayHelpers\DuplicateMapItemException;
use Ingenerator\PHPUtils\ArrayHelpers\UniqueMap;
use OutOfBoundsException;
use PHPUnit\Framework\TestCase;

use function iterator_to_array;

final class UniqueMapTest extends TestCase
{
    public function testItReturnsEntriesByKey(): void
    {
        $bar = new class { };
        $subject = new UniqueMap(
            [
                'foo' => 'some-foo',
                'bar' => $bar,
                'nully' => null,
            ]
        );
        $this->assertSame($bar, $subject['bar']);
        $this->assertSame('some-foo', $subject['foo']);
        $this->assertNull($subject['nully']);
    }

    public function testItThrowsOnAccessToUndefinedKey(): void
    {
        $subject = new UniqueMap(['any' => 'item']);
        $this->expectException(OutOfBoundsException::class);
        $f = $subject['other-item'];
    }

    public function testItAcceptsNewItems(): void
    {
        $new1 = new class { };
        $subject = new UniqueMap(['any' => 'item']);
        /* @phpstan-ignore offsetAssign.valueType (don't need to enforce collection type in this test) */
        $subject['new1'] = $new1;
        $subject['new2'] = 'some-string';
        /* @phpstan-ignore offsetAssign.valueType (don't need to enforce collection type in this test) */
        $subject['new3'] = null;

        $this->assertSame($new1, $subject['new1']);
        $this->assertSame('some-string', $subject['new2']);
        $this->assertNull($subject['new3']);
    }

    public function testItThrowsOnAttemptToOverwriteKey(): void
    {
        $subject = new UniqueMap(['any' => 'item']);

        try {
            $subject['any'] = 'anything';
            $this->fail('Expected an exception, none got');
        } catch (DuplicateMapItemException $e) {
            $this->assertStringContainsString('any', $e->getMessage(), 'Includes key in exception message');
            $this->assertSame('item', $subject['any'], 'Original item unmodified');
        }
    }

    public function testItReportsIfAnItemExists(): void
    {
        $subject = new UniqueMap(['any' => 'item']);
        $subject['other'] = 'thing';

        $this->assertSame(
            ['any' => true, 'other' => true, 'random' => false],
            [
                'any' => isset($subject['any']),
                'other' => isset($subject['other']),
                'random' => isset($subject['random']),
            ]
        );
    }

    public function testItAllowsItemsToBeDeletedAndReinserted(): void
    {
        $subject = new UniqueMap(['any' => 'item']);

        unset($subject['any']);
        $this->assertFalse(isset($subject['any']), 'not set after deletion');
        try {
            $f = $subject['any'];
            $this->fail('Cannot access key after removal');
        } catch (OutOfBoundsException) {
            // Expected
        }

        $subject['any'] = 'new item';
        $this->assertTrue(isset($subject['any']));
        $this->assertSame('new item', $subject['any']);
    }

    public function testItIsIterable(): void
    {
        $subject = new UniqueMap(['any' => 'item']);
        $subject['new'] = 'new1';
        $subject['other'] = 'new2';
        /* @phpstan-ignore offsetAssign.valueType (don't need to enforce collection type in this test) */
        $subject['nully'] = null;

        $this->assertSame(
            ['any' => 'item', 'new' => 'new1', 'other' => 'new2', 'nully' => null],
            iterator_to_array($subject)
        );
    }

    public function testItIsCountable(): void
    {
        $subject = new UniqueMap(['any' => 'item']);
        $subject['new'] = 'new1';
        $subject['other'] = 'new2';

        $this->assertCount(3, $subject);
    }

    public function testItSupportsConditionalReadLikeAnArray(): void
    {
        $subject = new UniqueMap(['i-exist' => 'ok']);
        $this->assertSame('any-default', $subject['i do not'] ?? 'any-default');
    }
}
