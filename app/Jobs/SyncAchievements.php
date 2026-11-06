<?php

namespace App\Jobs;

use App\Models\LinkedAccount;
use App\Services\AchievementSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Runs after a library sync. A failure here only means achievements are stale: it never touches the sync result. */
class SyncAchievements implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 900;

    public function __construct(public int $linkedAccountId)
    {
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping("achievements-{$this->linkedAccountId}"))->dontRelease()->expireAfter(1200)];
    }

    public function handle(AchievementSyncService $service): void
    {
        $account = LinkedAccount::find($this->linkedAccountId);

        if (! $account) {
            return;
        }

        try {
            $service->sync($account);
        } catch (Throwable $e) {
            Log::warning("gamehub: achievements for account {$account->id} failed: ".$e->getMessage());
        }
    }
}
