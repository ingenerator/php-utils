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


    public function test_it_returns_entries_by_key()
    {
        $bar = new class { };
        $subject = new UniqueMap(
            [
                'foo' => 'some-foo',
                'bar' => $bar,
            ]
        );
        $this->assertSame($bar, $subject['bar']);
        $this->assertSame('some-foo', $subject['foo']);
    }

    public function test_it_throws_on_access_to_undefined_key()
    {
        $subject = new UniqueMap(['any' => 'item']);
        $this->expectException(OutOfBoundsException::class);
        $subject['other-item'];
    }

    public function test_it_accepts_new_items()
    {
        $new1 = new class { };
        $subject = new UniqueMap(['any' => 'item']);
        $subject['new1'] = $new1;
        $subject['new2'] = 'some-string';

        $this->assertSame($new1, $subject['new1']);
        $this->assertSame('some-string', $subject['new2']);
    }

    public function test_it_throws_on_attempt_to_overwrite_key()
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

    public function test_it_reports_if_an_item_exists()
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

    public function test_it_allows_items_to_be_deleted_and_reinserted()
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

    public function test_it_is_iterable()
    {
        $subject = new UniqueMap(['any' => 'item']);
        $subject['new'] = 'new1';
        $subject['other'] = 'new2';

        $this->assertSame(
            ['any' => 'item', 'new' => 'new1', 'other' => 'new2'],
            iterator_to_array($subject)
        );
    }

    public function test_it_is_countable()
    {
        $subject = new UniqueMap(['any' => 'item']);
        $subject['new'] = 'new1';
        $subject['other'] = 'new2';

        $this->assertSame(3, count($subject));
    }

    public function test_it_supports_conditional_read_like_an_array()
    {
        $subject = new UniqueMap(['i-exist' => 'ok']);
        $this->assertSame('any-default', $subject['i do not'] ?? 'any-default');
    }

}
