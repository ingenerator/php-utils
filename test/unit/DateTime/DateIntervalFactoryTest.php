<?php

namespace test\unit\Ingenerator\PHPUtils\DateTime;

use DateInterval;
use Ingenerator\PHPUtils\DateTime\DateIntervalFactory;
use PHPUnit\Framework\TestCase;

class DateIntervalFactoryTest extends TestCase
{
    public function provider_shorthand_single_part()
    {
        return [
            'seconds' => [fn() => DateIntervalFactory::seconds(15), new DateInterval('PT15S')],
            'minutes' => [fn() => DateIntervalFactory::minutes(3), new DateInterval('PT3M')],
            'hours'   => [fn() => DateIntervalFactory::hours(5), new DateInterval('PT5H')],
            'days'    => [fn() => DateIntervalFactory::days(24), new DateInterval('P24D')],
            'months'  => [fn() => DateIntervalFactory::months(14), new DateInterval('P14M')],
            'years'   => [fn() => DateIntervalFactory::years(2), new DateInterval('P2Y')],
        ];
    }

    /**
     * @dataProvider provider_shorthand_single_part
     */
    public function test_from_shorthand_single_part(callable $creator, DateInterval $expect)
    {
        $this->assertEquals($expect, $creator());
    }

}