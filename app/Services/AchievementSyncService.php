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
        $bySimpleTitle = null;
        $updated = 0;

        foreach ($provider->fetchAchievements($account) as $progress) {
            $entries = $progress->externalId !== null
                ? $this->entriesByExternalId($account, $progress)
                : $this->entriesByTitle($account, $progress, $byTitle, $bySimpleTitle);

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
    private function entriesByTitle(LinkedAccount $account, AchievementProgress $progress, ?Collection &$index, ?Collection &$simpleIndex): Collection
    {
        $index ??= LibraryEntry::with(['game', 'platform'])
            ->where('user_id', $account->user_id)
            ->where('linked_account_id', $account->id)
            ->get()
            ->groupBy(fn (LibraryEntry $e) => IgdbClient::normalize($e->game->title));

        $matches = $index->get(IgdbClient::normalize((string) $progress->title), collect());

        // No exact match: try again ignoring edition words, so "Game Deluxe Edition" or "Game PS5" still finds "Game".
        if ($matches->isEmpty()) {
            $simpleIndex ??= $index->flatten(1)->groupBy(fn (LibraryEntry $e) => self::simplify($e->game->title));
            $matches = $simpleIndex->get(self::simplify((string) $progress->title), collect());
        }

        if ($progress->platformSlug !== null) {
            $samePlatform = $matches->filter(fn (LibraryEntry $e) => $e->platform->slug === $progress->platformSlug);

            // A PS4 game played on a PS5 is listed under one platform and trophied under the other: fall back to the title.
            if ($samePlatform->isNotEmpty()) {
                return $samePlatform->values();
            }
        }

        return $matches->values();
    }

    /** Title with edition / platform words removed, for the fallback match. Equality only, never a partial match. */
    public static function simplify(string $title): string
    {
        $t = IgdbClient::normalize($title);
        // "Alan Wake II" and "Alan Wake 2" are the same game. Single "i", "v" and "x" are left alone (too ambiguous).
        $t = preg_replace_callback('/\b(ii|iii|iv|vi|vii|viii|ix)\b/u', fn ($m) => (string) ['ii' => 2, 'iii' => 3, 'iv' => 4, 'vi' => 6, 'vii' => 7, 'viii' => 8, 'ix' => 9][$m[1]], $t) ?? $t;
        $t = preg_replace('/\b(ps4|ps5|ps vita|playstation 4|playstation 5|digital|deluxe|ultimate|standard|premium|special|collector s|complete|definitive|enhanced|anniversary|goty|game of the year|remastered|remaster|edition|version|director s cut|cross gen|bundle|trophy set|trophies|trophy)\b/u', ' ', $t) ?? $t;
        $t = preg_replace('/^the\s+/u', '', trim(preg_replace('/\s+/u', ' ', $t) ?? $t)) ?? $t;

        return trim($t);
    }
}
