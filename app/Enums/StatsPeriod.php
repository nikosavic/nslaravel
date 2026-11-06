<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

enum StatsPeriod: string
{
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';
    case All = 'all';

    public function label(): string
    {
        return match ($this) {
            self::Week => 'Last 7 days',
            self::Month => 'Last 30 days',
            self::Year => 'Last 12 months',
            self::All => 'All time',
        };
    }

    public function short(): string
    {
        return match ($this) {
            self::Week => 'Week',
            self::Month => 'Month',
            self::Year => 'Year',
            self::All => 'All time',
        };
    }

    /**
     * First and last day covered, ending at $now. "All" starts at $earliest (the first recorded day).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function range(CarbonImmutable $now, ?CarbonImmutable $earliest = null): array
    {
        $end = $now->endOfDay();

        return match ($this) {
            self::Week => [$now->subDays(6)->startOfDay(), $end],
            self::Month => [$now->subDays(29)->startOfDay(), $end],
            self::Year => [$now->subMonthsNoOverflow(11)->startOfMonth(), $end],
            self::All => [($earliest ?? $now)->startOfMonth(), $end],
        };
    }

    /** Bar size: day, month or year. "All" switches to years once it spans more than two. */
    public function grain(CarbonImmutable $start, CarbonImmutable $end): string
    {
        return match ($this) {
            self::Week, self::Month => 'day',
            self::Year => 'month',
            self::All => $start->diffInMonths($end) > 24 ? 'year' : 'month',
        };
    }

    /** The equal-length stretch right before this one, for "up 12% on the period before". Null for All. */
    public function previous(CarbonImmutable $start, CarbonImmutable $end): ?array
    {
        return match ($this) {
            self::Week => [$start->subDays(7), $start->subDay()->endOfDay()],
            self::Month => [$start->subDays(30), $start->subDay()->endOfDay()],
            self::Year => [$start->subMonthsNoOverflow(12), $start->subDay()->endOfDay()],
            self::All => null,
        };
    }
}
