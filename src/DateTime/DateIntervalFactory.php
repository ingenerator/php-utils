<?php

namespace Ingenerator\PHPUtils\DateTime;

use DateInterval;

class DateIntervalFactory
{

    public static function days(int $days): DateInterval
    {
        return new DateInterval('P'.$days.'D');
    }

    public static function hours(int $int): DateInterval
    {
        return new DateInterval('PT'.$int.'H');
    }

    public static function minutes(int $int): DateInterval
    {
        return new DateInterval('PT'.$int.'M');
    }

    public static function months(int $int): DateInterval
    {
        return new DateInterval('P'.$int.'M');
    }

    public static function seconds(int $seconds): DateInterval
    {
        return new DateInterval('PT'.$seconds.'S');
    }

    public static function years(int $int): DateInterval
    {
        return new DateInterval('P'.$int.'Y');
    }
}