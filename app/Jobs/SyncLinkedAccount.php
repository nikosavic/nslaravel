<?php

namespace App\Jobs;

use App\Models\LinkedAccount;
use App\Services\LibrarySyncService;
use App\Services\Providers\Contracts\ProvidesAchievements;
use App\Services\Providers\ProviderRegistry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class SyncLinkedAccount implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;      // failures are recorded in sync_runs; no blind retry loops against provider APIs
    public int $timeout = 600;

    public function __construct(public int $linkedAccountId)
    {
    }

    public function middleware(): array
    {
        // A second sync for the same account while one is running is simply dropped.
        return [(new WithoutOverlapping("sync-account-{$this->linkedAccountId}"))->dontRelease()->expireAfter(900)];
    }

    public function handle(LibrarySyncService $sync, ProviderRegistry $providers): void
    {
        $account = LinkedAccount::find($this->linkedAccountId);

        if (! $account) {
            return;
        }

        $run = $sync->sync($account);

        // Achievements are a second, slower pass that only makes sense once the games themselves are in.
        if ($run->status === 'success' && $providers->for($account->provider) instanceof ProvidesAchievements) {
            SyncAchievements::dispatch($account->id);
        }
    }
}
