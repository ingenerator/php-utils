<?php

declare(strict_types=1);

namespace test\unit\Ingenerator\PHPUtils\ArrayHelpers;

use Ingenerator\PHPUtils\ArrayHelpers\DuplicateMapItemException;
use Ingenerator\PHPUtils\ArrayHelpers\UniqueMap;
use OutOfBoundsException;
use PHPUnit\Framework\TestCase;

use function iterator_to_array;

class UniqueMapTest extends TestCase
{
    public function testItReturnsEntriesByKey()
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
        $this->assertSame(null, $subject['nully']);
    }

    public function testItThrowsOnAccessToUndefinedKey()
    {
        $subject = new UniqueMap(['any' => 'item']);
        $this->expectException(OutOfBoundsException::class);
        $subject['other-item'];
    }

    public function testItAcceptsNewItems()
    {
        $new1 = new class { };
        $subject = new UniqueMap(['any' => 'item']);
        $subject['new1'] = $new1;
        $subject['new2'] = 'some-string';
        $subject['new3'] = null;

        $this->assertSame($new1, $subject['new1']);
        $this->assertSame('some-string', $subject['new2']);
        $this->assertSame(null, $subject['new3']);
    }

    public function testItThrowsOnAttemptToOverwriteKey()
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

    public function testItReportsIfAnItemExists()
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

    public function testItAllowsItemsToBeDeletedAndReinserted()
    {
        $subject = new UniqueMap(['any' => 'item']);

        unset($subject['any']);
        $this->assertFalse(isset($subject['any']), 'not set after deletion');
        try {
            $subject['any'];
            $this->fail('Cannot access key after removal');
        } catch (OutOfBoundsException) {
            // Expected
        }

        $subject['any'] = 'new item';
        $this->assertTrue(isset($subject['any']));
        $this->assertSame('new item', $subject['any']);
    }

    public function testItIsIterable()
    {
        $subject = new UniqueMap(['any' => 'item']);
        $subject['new'] = 'new1';
        $subject['other'] = 'new2';
        $subject['nully'] = null;

        $this->assertSame(
            ['any' => 'item', 'new' => 'new1', 'other' => 'new2', 'nully' => null],
            iterator_to_array($subject)
        );
    }

    public function testItIsCountable()
    {
        $subject = new UniqueMap(['any' => 'item']);
        $subject['new'] = 'new1';
        $subject['other'] = 'new2';

        $this->assertSame(3, count($subject));
    }

    public function testItSupportsConditionalReadLikeAnArray()
    {
        $subject = new UniqueMap(['i-exist' => 'ok']);
        $this->assertSame('any-default', $subject['i do not'] ?? 'any-default');
    }
}
