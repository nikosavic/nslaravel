<?php

namespace App\Http\Controllers;

use App\Enums\StatsPeriod;
use App\Models\LibraryEntry;
use App\Services\StatsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /** Every game in the library, one row each. */
    public function library(): StreamedResponse
    {
        return $this->csv('gamehub-library-'.now()->format('Y-m-d').'.csv', function ($out) {
            fputcsv($out, ['Title', 'Platform', 'Status', 'Hours played', 'Rating', 'Last played', 'Started', 'Finished', 'Achievements unlocked', 'Achievements total']);

            LibraryEntry::with(['game', 'platform'])
                ->where('user_id', Auth::id())
                ->orderBy('id')
                ->chunk(500, function ($entries) use ($out) {
                    foreach ($entries as $entry) {
                        fputcsv($out, array_map([self::class, 'safe'], [
                            $entry->game->title,
                            $entry->platform->name,
                            $entry->status->label(),
                            round($entry->playtime_minutes / 60, 1),
                            $entry->rating,
                            $entry->last_played_at?->toDateString(),
                            $entry->started_at?->toDateString(),
                            $entry->finished_at?->toDateString(),
                            $entry->achievements_unlocked,
                            $entry->achievements_total,
                        ]));
                    }
                });
        });
    }

    /** The bars and the top-games table of the Stats page for one period. */
    public function stats(Request $request, StatsService $stats): StreamedResponse
    {
        $period = StatsPeriod::tryFrom((string) $request->query('period')) ?? StatsPeriod::Month;
        $report = $stats->report((int) Auth::id(), $period);

        return $this->csv("gamehub-stats-{$period->value}-".now()->format('Y-m-d').'.csv', function ($out) use ($report) {
            fputcsv($out, ['Period', 'Hours played']);

            foreach ($report['series'] as $bucket) {
                fputcsv($out, [$bucket['key'], round($bucket['minutes'] / 60, 2)]);
            }

            fputcsv($out, []);
            fputcsv($out, ['Top games', 'Platform', 'Hours played']);

            foreach ($report['top'] as $row) {
                fputcsv($out, array_map([self::class, 'safe'], [
                    $row['entry']->game->title, $row['entry']->platform->name, round($row['minutes'] / 60, 2),
                ]));
            }
        });
    }

    /** A title starting with = + - @ would run as a formula when the CSV is opened in a spreadsheet. */
    public static function safe(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }

    private function csv(string $name, callable $write): StreamedResponse
    {
        return response()->streamDownload(function () use ($write) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel reads UTF-8 titles correctly
            $write($out);
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
