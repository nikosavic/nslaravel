<?php

namespace App\Services;

use App\Models\GameExternalId;
use App\Models\LibraryEntry;
use App\Models\LinkedAccount;
use App\Services\Igdb\IgdbClient;
use App\Services\Providers\Contracts\ProvidesAchievements;
use App\Services\Providers\DTO\AchievementProgress;
use App\Services\Providers\ProviderRegistry;
use Illuminate\Support\Collection;

/**
 * Stores achievement / trophy progress on library entries. Steam rows are matched by appid, PlayStation rows by
 * title (Sony's trophy list carries its own ids that do not match the game list), so an unmatched title is skipped.
 */
class AchievementSyncService
{
    public function __construct(private ProviderRegistry $providers)
    {
    }

    /** @return int how many entries were updated */
    public function sync(LinkedAccount $account): int
    {
        $provider = $this->providers->for($account->provider);

        if (! $provider instanceof ProvidesAchievements) {
            return 0;
        }

        $byTitle = null;
        $updated = 0;

        foreach ($provider->fetchAchievements($account) as $progress) {
            $entries = $progress->externalId !== null
                ? $this->entriesByExternalId($account, $progress)
                : $this->entriesByTitle($account, $progress, $byTitle);

            foreach ($entries as $entry) {
                $entry->update([
                    'achievements_unlocked' => $progress->unlocked,
                    'achievements_total' => $progress->total,
                    'trophies' => $progress->trophies,
                    'achievements_synced_at' => now(),
                ]);
                $updated++;
            }
        }

        return $updated;
    }

    /** @return Collection<int, LibraryEntry> */
    private function entriesByExternalId(LinkedAccount $account, AchievementProgress $progress): Collection
    {
        $gameId = GameExternalId::where('provider', $account->provider->value)
            ->where('external_id', $progress->externalId)
            ->value('game_id');

        if (! $gameId) {
            return collect();
        }

        return LibraryEntry::where('user_id', $account->user_id)
            ->where('game_id', $gameId)
            ->where('linked_account_id', $account->id)
            ->get();
    }

    /**
     * @param  Collection<string, Collection<int, LibraryEntry>>|null  $index  built once per run
     * @return Collection<int, LibraryEntry>
     */
    private function entriesByTitle(LinkedAccount $account, AchievementProgress $progress, ?Collection &$index): Collection
    {
        $index ??= LibraryEntry::with(['game', 'platform'])
            ->where('user_id', $account->user_id)
            ->where('linked_account_id', $account->id)
            ->get()
            ->groupBy(fn (LibraryEntry $e) => IgdbClient::normalize($e->game->title));

        $matches = $index->get(IgdbClient::normalize((string) $progress->title), collect());

        if ($progress->platformSlug !== null) {
            $samePlatform = $matches->filter(fn (LibraryEntry $e) => $e->platform->slug === $progress->platformSlug);

            // A PS4 game played on a PS5 is listed under one platform and trophied under the other: fall back to the title.
            if ($samePlatform->isNotEmpty()) {
                return $samePlatform->values();
            }
        }

        return $matches->values();
    }
}
