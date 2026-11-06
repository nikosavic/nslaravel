<?php

namespace App\Services;

use App\Models\LibraryEntry;
use App\Models\PlaytimeSnapshot;

/**
 * Keeps the history that makes weekly / monthly / yearly stats possible.
 * A row is written only when an entry's total changes (plus once, as the starting point, the first time it
 * is seen), so a library that is synced every six hours still stores one row per game per day at most.
 */
class PlaytimeSnapshotter
{
    public function record(LibraryEntry $entry): void
    {
        $minutes = (int) $entry->playtime_minutes;

        $last = PlaytimeSnapshot::where('library_entry_id', $entry->id)
            ->orderByDesc('taken_on')
            ->first();

        if ($last && (int) $last->minutes === $minutes) {
            return;
        }

        $today = now()->startOfDay();

        // whereDate: the date column may come back as "2026-11-06" (MySQL) or "2026-11-06 00:00:00" (SQLite).
        $snapshot = PlaytimeSnapshot::where('library_entry_id', $entry->id)
            ->whereDate('taken_on', $today->toDateString())
            ->first();

        if ($snapshot) {
            $snapshot->update(['minutes' => $minutes]);

            return;
        }

        PlaytimeSnapshot::create([
            'library_entry_id' => $entry->id,
            'taken_on' => $today,
            'minutes' => $minutes,
        ]);
    }
}
