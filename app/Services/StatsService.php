<?php

namespace App\Services;

use App\Enums\GameStatus;
use App\Enums\StatsPeriod;
use App\Models\LibraryEntry;
use App\Models\Platform;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Turns playtime snapshots into reports. Time played in a stretch is the growth of a game's total between
 * snapshots, credited to the day the growth was first seen (the sync day). History before the first sync
 * is unknown, so a game's first snapshot is a starting point and never counts as time played.
 */
class StatsService
{
    /**
     * @return array{
     *   period: StatsPeriod, start: CarbonImmutable, end: CarbonImmutable, grain: string, hasHistory: bool,
     *   minutes: int, previousMinutes: ?int, activeDays: int, gamesPlayed: int, finished: int, added: int,
     *   series: list<array{key:string,label:string,minutes:int}>,
     *   top: list<array{entry:LibraryEntry,minutes:int}>,
     *   platforms: list<array{name:string,color:string,minutes:int}>,
     *   genres: list<array{name:string,minutes:int}>
     * }
     */
    public function report(int $userId, StatsPeriod $period, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $first = $this->firstSnapshotDay($userId);

        [$start, $end] = $period->range($now, $first);
        $grain = $period->grain($start, $end);
        $previous = $period->previous($start, $end);

        // One pass over the history covers this period and the one before it.
        $deltas = $this->deltas($userId, ($previous[0] ?? $start), $end);

        $inRange = $deltas->filter(fn ($d) => $d['date'] >= $start->toDateString());
        $before = $previous
            ? $deltas->filter(fn ($d) => $d['date'] >= $previous[0]->toDateString() && $d['date'] <= $previous[1]->toDateString())
            : null;

        $perEntry = $inRange->groupBy('entry')->map(fn ($rows) => (int) $rows->sum('minutes'))->sortDesc();

        $entries = LibraryEntry::with(['game', 'platform'])
            ->whereIn('id', $perEntry->keys())
            ->get()
            ->keyBy('id');

        $top = $perEntry->take(8)->map(fn ($minutes, $id) => $entries->has($id) ? ['entry' => $entries[$id], 'minutes' => $minutes] : null)
            ->filter()->values()->all();

        $platformTotals = [];
        $genreTotals = [];

        foreach ($perEntry as $id => $minutes) {
            $entry = $entries[$id] ?? null;

            if (! $entry) {
                continue;
            }

            $name = $entry->platform->name;
            $platformTotals[$name] ??= ['name' => $name, 'color' => $entry->platform->color(), 'minutes' => 0];
            $platformTotals[$name]['minutes'] += $minutes;

            foreach ($entry->game->genres ?? [] as $genre) {
                $genreTotals[$genre] = ($genreTotals[$genre] ?? 0) + $minutes;
            }
        }

        usort($platformTotals, fn ($a, $b) => $b['minutes'] <=> $a['minutes']);
        arsort($genreTotals);

        $finishedStatuses = [GameStatus::Finished->value, GameStatus::Completed->value];

        return [
            'period' => $period,
            'start' => $start,
            'end' => $end,
            'grain' => $grain,
            'hasHistory' => $first !== null,
            'minutes' => (int) $perEntry->sum(),
            'previousMinutes' => $before ? (int) $before->sum('minutes') : null,
            'activeDays' => $inRange->pluck('date')->unique()->count(),
            'gamesPlayed' => $perEntry->count(),
            'finished' => LibraryEntry::where('user_id', $userId)->whereIn('status', $finishedStatuses)
                ->whereBetween('finished_at', [$start, $end])->count(),
            'added' => LibraryEntry::where('user_id', $userId)->whereBetween('created_at', [$start, $end])->count(),
            'series' => $this->series($inRange, $start, $end, $grain),
            'top' => $top,
            'platforms' => array_values($platformTotals),
            'genres' => collect($genreTotals)->take(8)->map(fn ($m, $g) => ['name' => (string) $g, 'minutes' => (int) $m])->values()->all(),
        ];
    }

    /** "+12%" / "-8%" style change against the previous stretch, or null when there is nothing to compare. */
    public static function change(int $now, ?int $before): ?int
    {
        if ($before === null || $before <= 0) {
            return null;
        }

        return (int) round(($now - $before) / $before * 100);
    }

    private function firstSnapshotDay(int $userId): ?CarbonImmutable
    {
        $day = DB::table('playtime_snapshots as s')
            ->join('library_entries as e', 'e.id', '=', 's.library_entry_id')
            ->where('e.user_id', $userId)
            ->min('s.taken_on');

        return $day ? CarbonImmutable::parse($day) : null;
    }

    /**
     * Growth of every game's total between consecutive snapshots, from $from to $to.
     *
     * @return \Illuminate\Support\Collection<int, array{entry:int,date:string,minutes:int}>
     */
    private function deltas(int $userId, CarbonImmutable $from, CarbonImmutable $to)
    {
        $rows = DB::table('playtime_snapshots as s')
            ->join('library_entries as e', 'e.id', '=', 's.library_entry_id')
            ->where('e.user_id', $userId)
            ->where('s.taken_on', '<=', $to->toDateString())
            ->orderBy('s.library_entry_id')
            ->orderBy('s.taken_on')
            ->select('s.library_entry_id', 's.taken_on', 's.minutes')
            ->cursor();

        $out = collect();
        $lastEntry = null;
        $previous = 0;
        $fromDay = $from->toDateString();

        foreach ($rows as $row) {
            $date = substr((string) $row->taken_on, 0, 10);

            if ($row->library_entry_id !== $lastEntry) {
                $lastEntry = $row->library_entry_id;
                $previous = (int) $row->minutes; // first snapshot: a starting point, not time played
                continue;
            }

            $grew = max(0, (int) $row->minutes - $previous);
            $previous = (int) $row->minutes;

            if ($grew > 0 && $date >= $fromDay) {
                $out->push(['entry' => (int) $row->library_entry_id, 'date' => $date, 'minutes' => $grew]);
            }
        }

        return $out;
    }

    /** Fixed buckets, so days or months with no play still get an (empty) bar. */
    private function series($deltas, CarbonImmutable $start, CarbonImmutable $end, string $grain): array
    {
        [$step, $keyFormat, $labelFormat] = match ($grain) {
            'day' => ['addDay', 'Y-m-d', 'j M'],
            'month' => ['addMonthNoOverflow', 'Y-m', 'M'],
            default => ['addYear', 'Y', 'Y'],
        };

        $buckets = [];

        for ($cursor = $start; $cursor <= $end; $cursor = $cursor->{$step}()) {
            $buckets[$cursor->format($keyFormat)] = [
                'key' => $cursor->format($keyFormat),
                'label' => $cursor->format($labelFormat),
                'minutes' => 0,
            ];
        }

        foreach ($deltas as $d) {
            $key = CarbonImmutable::parse($d['date'])->format($keyFormat);

            if (isset($buckets[$key])) {
                $buckets[$key]['minutes'] += $d['minutes'];
            }
        }

        return array_values($buckets);
    }
}
