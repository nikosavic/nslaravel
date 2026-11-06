<?php

namespace App\Services;

use App\Models\Game;
use App\Models\LibraryEntry;
use App\Services\Igdb\IgdbClient;
use Illuminate\Support\Collection;

/**
 * Finds games that are one title stored twice (IGDB could not or has not matched them) and merges them.
 * Only an exact match after normalising case, trademark signs and punctuation counts as a candidate, so
 * "Elden Ring" and "ELDEN RING" are offered but "Resident Evil 4" and "Resident Evil 4 Remake" are not.
 */
class DuplicateMerger
{
    public function __construct(private GameResolver $resolver)
    {
    }

    /**
     * Groups of two or more distinct games among this user's library that share a normalised title.
     *
     * @return Collection<int, Collection<int, array{game: Game, platforms: list<string>}>>
     */
    public function groups(int $userId): Collection
    {
        $entries = LibraryEntry::with(['game', 'platform'])->where('user_id', $userId)->get();

        return $entries
            ->groupBy(fn (LibraryEntry $e) => IgdbClient::normalize($e->game->title))
            ->filter(fn (Collection $group, string $key) => $key !== '' && $group->pluck('game_id')->unique()->count() > 1)
            ->map(fn (Collection $group) => $group->groupBy('game_id')->map(fn (Collection $rows) => [
                'game' => $rows->first()->game,
                'platforms' => $rows->pluck('platform.name')->unique()->values()->all(),
            ])->values())
            ->values();
    }

    public function count(int $userId): int
    {
        return $this->groups($userId)->count();
    }

    /**
     * Merges $drop into $keep. Refused (false) when either game is not in this user's library, when they are not
     * a listed duplicate pair, or when IGDB has matched them to two different games.
     */
    public function merge(int $userId, int $keepId, int $dropId): bool
    {
        if ($keepId === $dropId) {
            return false;
        }

        $group = $this->groups($userId)->first(
            fn (Collection $g) => $g->contains(fn ($row) => $row['game']->id === $keepId)
                && $g->contains(fn ($row) => $row['game']->id === $dropId)
        );

        if (! $group) {
            return false;
        }

        $keep = $group->first(fn ($row) => $row['game']->id === $keepId)['game'];
        $drop = $group->first(fn ($row) => $row['game']->id === $dropId)['game'];

        if ($keep->igdb_id && $drop->igdb_id && $keep->igdb_id !== $drop->igdb_id) {
            return false;
        }

        // Keep whatever the surviving game is missing.
        $fill = [];

        foreach (['igdb_id', 'cover_url', 'release_date', 'summary', 'genres', 'enriched_at', 'ttb_main_minutes', 'ttb_complete_minutes', 'ttb_checked_at'] as $field) {
            if ($keep->{$field} === null && $drop->{$field} !== null) {
                $fill[$field] = $drop->{$field};
            }
        }

        // igdb_id is unique: free it on the dropped row before the kept row takes it.
        if (isset($fill['igdb_id'])) {
            $drop->update(['igdb_id' => null]);
        }

        $keep->update($fill);
        $this->resolver->mergeInto($drop, $keep);

        return true;
    }
}
