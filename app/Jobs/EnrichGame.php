<?php

namespace App\Jobs;

use App\Models\Game;
use App\Services\GameResolver;
use App\Services\Igdb\IgdbClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Sleep;

/**
 * Looks a game up on IGDB. If another Game row already owns that igdb_id (say the same title
 * arrived from Steam and from PlayStation), the two rows are merged: that is the cross-platform match.
 */
class EnrichGame implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public array $backoff = [10, 60];

    public function __construct(public int $gameId)
    {
    }

    public function middleware(): array
    {
        // One IGDB call at a time keeps us under their rate limit.
        return [(new WithoutOverlapping('igdb'))->releaseAfter(2)->expireAfter(60)];
    }

    public function handle(IgdbClient $igdb, GameResolver $resolver): void
    {
        $game = Game::with('externalIds')->find($this->gameId);

        if (! $game || $game->enriched_at || ! $igdb->isConfigured()) {
            return;
        }

        $match = null;
        $steam = $game->externalIds->firstWhere('provider', 'steam');

        if ($steam) {
            try {
                $match = $igdb->findBySteamAppId($steam->external_id);
            } catch (\RuntimeException) {
                $match = null; // fall back to the title search below
            }
        }

        $match ??= $igdb->searchByTitle($game->title);

        if (! $match) {
            $game->update(['enriched_at' => now()]); // attempted; igdb_id stays null
            Sleep::for(300)->milliseconds();

            return;
        }

        $canonical = Game::where('igdb_id', $match['igdb_id'])->where('id', '!=', $game->id)->first();

        if ($canonical) {
            $resolver->mergeInto($game, $canonical);
        } else {
            $game->update($match + ['enriched_at' => now()]);
        }

        Sleep::for(300)->milliseconds();
    }
}
