<?php

namespace App\Support;

class Format
{
    /**
     * Minutes as a number and a unit for display: [ '12.5', 'hours' ], [ '40', 'minutes' ]. Zero gives ['0', 'hours'].
     *
     * @return array{0:string,1:string}
     */
    public static function duration(int $minutes): array
    {
        if ($minutes <= 0) {
            return ['0', 'hours'];
        }

        if ($minutes < 60) {
            return [(string) $minutes, $minutes === 1 ? 'minute' : 'minutes'];
        }

        $hours = round($minutes / 60, 1);

        return [rtrim(rtrim(number_format($hours, 1), '0'), '.'), $hours == 1.0 ? 'hour' : 'hours'];
    }

    public static function durationText(int $minutes): string
    {
        return implode(' ', self::duration($minutes));
    }
}
