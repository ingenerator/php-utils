<?php

declare(strict_types=1);

namespace test\unit\Ingenerator\PHPUtils\DateTime;

use Closure;
use DateInterval;
use Ingenerator\PHPUtils\DateTime\DateIntervalFactory;
use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DateIntervalFactoryTest extends TestCase
{
    /**
     * @return Iterator<string, array<int, (Closure(): DateInterval | DateInterval)>>
     */
    public static function provider_shorthand_single_part(): Iterator
    {
        yield 'seconds' => [fn (): DateInterval => DateIntervalFactory::seconds(15), new DateInterval('PT15S')];
        yield 'minutes' => [fn (): DateInterval => DateIntervalFactory::minutes(3), new DateInterval('PT3M')];
        yield 'hours' => [fn (): DateInterval => DateIntervalFactory::hours(5), new DateInterval('PT5H')];
        yield 'days' => [fn (): DateInterval => DateIntervalFactory::days(24), new DateInterval('P24D')];
        yield 'months' => [fn (): DateInterval => DateIntervalFactory::months(14), new DateInterval('P14M')];
        yield 'years' => [fn (): DateInterval => DateIntervalFactory::years(2), new DateInterval('P2Y')];
    }

    #[DataProvider('provider_shorthand_single_part')]
    public function testFromShorthandSinglePart(callable $creator, DateInterval $expect): void
    {
        $this->assertEquals($expect, $creator());
    }
}
