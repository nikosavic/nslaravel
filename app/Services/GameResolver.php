<?php

namespace App\Services;

use App\Enums\ProviderType;
use App\Jobs\EnrichGame;
use App\Models\Game;
use App\Models\GameExternalId;
use App\Models\LibraryEntry;
use App\Services\Igdb\IgdbClient;
use App\Services\Providers\DTO\ProviderGame;
use Illuminate\Support\Facades\DB;

/**
 * Maps a provider's game to the single canonical Game row, and merges duplicates once IGDB
 * proves two rows are the same game (this is what makes the library cross-platform).
 */
class GameResolver
{
    public function __construct(private IgdbClient $igdb)
    {
    }

    public function resolve(ProviderType $provider, ProviderGame $providerGame): Game
    {
        $known = GameExternalId::with('game')
            ->where('provider', $provider->value)
            ->where('external_id', $providerGame->externalId)
            ->first();

        if ($known) {
            return $known->game;
        }

        $game = DB::transaction(function () use ($provider, $providerGame) {
            $game = Game::create([
                'title' => $providerGame->title,
                'cover_url' => $providerGame->coverUrl,
            ]);

            $game->externalIds()->create([
                'provider' => $provider->value,
                'external_id' => $providerGame->externalId,
            ]);

            return $game;
        });

        if ($this->igdb->isConfigured()) {
            EnrichGame::dispatch($game->id);
        }

        return $game;
    }

    /** Manual adds: reuse a game with the same title (case-insensitive) or create it. */
    public function findOrCreateByTitle(string $title): Game
    {
        $title = trim($title);

        $game = Game::whereRaw('LOWER(title) = ?', [mb_strtolower($title)])->first();

        if (! $game) {
            $game = Game::create(['title' => $title]);

            if ($this->igdb->isConfigured()) {
                EnrichGame::dispatch($game->id);
            }
        }

        return $game;
    }

    /** Move everything from $from onto $into, then delete $from. */
    public function mergeInto(Game $from, Game $into): void
    {
        DB::transaction(function () use ($from, $into) {
            $from->externalIds()->update(['game_id' => $into->id]);

            LibraryEntry::where('game_id', $from->id)->get()->each(function (LibraryEntry $entry) use ($into) {
                $clash = LibraryEntry::where('user_id', $entry->user_id)
                    ->where('platform_id', $entry->platform_id)
                    ->where('game_id', $into->id)
                    ->first();

                if (! $clash) {
                    $entry->update(['game_id' => $into->id]);

                    return;
                }

                // Same user, same platform, same game: keep the one with more playtime, drop the other.
                if ($entry->playtime_minutes > $clash->playtime_minutes) {
                    $clash->delete();
                    $entry->update(['game_id' => $into->id]);
                } else {
                    $entry->delete();
                }
            });

            $from->delete();
        });
    }
}
