<?php

namespace App\Jobs;

use App\Models\Game;
use App\Services\Igdb\IgdbClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Sleep;

/** Asks IGDB how long a matched game takes to beat. One attempt per game; ttb_checked_at records it. */
class FetchTimeToBeat implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public array $backoff = [10, 60];

    public function __construct(public int $gameId)
    {
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('igdb'))->releaseAfter(2)->expireAfter(60)];
    }

    public function handle(IgdbClient $igdb): void
    {
        $game = Game::find($this->gameId);

        if (! $game || ! $game->igdb_id || $game->ttb_checked_at || ! $igdb->isConfigured()) {
            return;
        }

        $times = $igdb->timeToBeat($game->igdb_id);

        $game->update([
            'ttb_main_minutes' => $times['main'] ?? null,
            'ttb_complete_minutes' => $times['complete'] ?? null,
            'ttb_checked_at' => now(),
        ]);

        Sleep::for(300)->milliseconds();
    }
}
