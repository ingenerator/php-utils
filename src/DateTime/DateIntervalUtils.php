<?php

namespace Ingenerator\PHPUtils\DateTime;

use DateInterval;
use function array_diff;
use function array_filter;
use function array_intersect_key;
use function array_keys;
use function array_pop;
use function count;
use function implode;

class DateIntervalUtils
{
    private const SUPPORTED_HUMAN_COMPONENTS = [
        'y' => 'year',
        'm' => 'month',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    ];

    public static function toHuman(DateInterval $interval): string
    {
        $components = array_filter((array) $interval);

        $supported_components = array_intersect_key(static::SUPPORTED_HUMAN_COMPONENTS, $components);

        if (count($supported_components) !== count($components)) {
            $key_list = implode(', ', array_diff(array_keys($components), array_keys($supported_components)));
            throw new \InvalidArgumentException(
                'Cannot humanise a DateInterval with unsupported components: '.$key_list
            );
        }

        $parts = [];
        foreach ($supported_components as $key => $human_value) {
            $qty     = $components[$key] ?? NULL;
            $parts[] = $qty.' '.$human_value.($qty > 1 ? 's' : '');
        }

        $last_part   = array_pop($parts);
        $second_last = array_pop($parts);

        return implode(
            '',
            [
                implode(', ', $parts),
                $parts !== [] ? ', ' : '',
                $second_last,
                $second_last ? ' and ' : '',
                $last_part,
            ]
        );
    }
}
