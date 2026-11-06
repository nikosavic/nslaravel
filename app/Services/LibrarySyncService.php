<?php

namespace App\Services;

use App\Enums\GameStatus;
use App\Models\LibraryEntry;
use App\Models\LinkedAccount;
use App\Models\Platform;
use App\Models\SyncRun;
use App\Services\Providers\Exceptions\ProviderException;
use App\Services\Providers\ProviderRegistry;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Runs one provider sync for one linked account and records the outcome in sync_runs.
 *
 * Rules:
 *  - providers feed ownership and playtime only; status, rating and notes always belong to the user
 *  - new entries start as Unsorted so a 500-game Steam import doesn't pretend you're playing everything
 *  - if playtime grows on an Unsorted/Backlog/Wishlist/On hold game, it flips to Playing (configurable)
 *  - every change in playtime is also stored as a snapshot, which is what the Stats page is built from
 *  - a failure is recorded and returned, never thrown, so one broken provider can't block the rest
 */
class LibrarySyncService
{
    public function __construct(
        private ProviderRegistry $providers,
        private GameResolver $resolver,
        private PlaytimeSnapshotter $snapshots,
    ) {
    }

    /** Platforms that report no hours (Xbox) still show a newer "last played", which is just as good a signal. */
    private function playedAgain(?\DateTimeInterface $before, ?\DateTimeInterface $after): bool
    {
        return $before !== null && $after !== null && $after > $before;
    }

    public function sync(LinkedAccount $account): SyncRun
    {
        $run = $account->syncRuns()->create(['status' => 'running', 'started_at' => now()]);
        $seen = $created = $updated = 0;

        try {
            $provider = $this->providers->for($account->provider);
            $platforms = Platform::pluck('id', 'slug');

            foreach ($provider->fetchLibrary($account) as $providerGame) {
                $seen++;

                $platformId = $platforms->get($providerGame->platformSlug);

                if (! $platformId) {
                    Log::warning("gamehub: unknown platform slug [{$providerGame->platformSlug}], skipping {$providerGame->title}");

                    continue;
                }

                $game = $this->resolver->resolve($account->provider, $providerGame);

                $entry = LibraryEntry::firstOrNew([
                    'user_id' => $account->user_id,
                    'game_id' => $game->id,
                    'platform_id' => $platformId,
                ]);

                $isNew = ! $entry->exists;
                $previousPlaytime = (int) $entry->playtime_minutes;
                $previousLastPlayed = $entry->last_played_at;

                if ($isNew) {
                    $entry->status = GameStatus::Unsorted;
                }

                $entry->linked_account_id = $account->id;
                $entry->source = $account->provider->value;
                $entry->playtime_minutes = max(0, $providerGame->playtimeMinutes);
                $entry->last_played_at = $providerGame->lastPlayedAt ?? $entry->last_played_at;

                if (
                    ! $isNew
                    && config('gamehub.auto_promote')
                    && ($entry->playtime_minutes > $previousPlaytime || $this->playedAgain($previousLastPlayed, $entry->last_played_at))
                    && $entry->status->canAutoPromote()
                ) {
                    $entry->status = GameStatus::Playing;
                    $entry->started_at ??= $entry->last_played_at ?? now();
                }

                $entry->save();
                $this->snapshots->record($entry);

                if ($entry->wasRecentlyCreated) {
                    $created++;
                } elseif ($entry->wasChanged()) {
                    $updated++;
                }
            }

            $run->update([
                'status' => 'success',
                'finished_at' => now(),
                'games_seen' => $seen,
                'created_count' => $created,
                'updated_count' => $updated,
            ]);
            $account->update(['last_synced_at' => now(), 'last_status' => 'ok']);
        } catch (Throwable $e) {
            if (! $e instanceof ProviderException) {
                report($e); // unexpected: make sure it reaches the log
            }

            $run->update([
                'status' => 'failed',
                'finished_at' => now(),
                'games_seen' => $seen,
                'created_count' => $created,
                'updated_count' => $updated,
                'error' => Str::limit($e->getMessage(), 500),
            ]);
            $account->update(['last_status' => 'error']);
        }

        return $run->fresh();
    }
}
