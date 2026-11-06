<?php

namespace Tests\Feature;

use App\Jobs\EnrichGame;
use App\Models\Game;
use App\Models\LibraryEntry;
use App\Models\Platform;
use App\Models\User;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class EnrichGameTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlatformSeeder::class);
        Sleep::fake();

        config(['gamehub.igdb.client_id' => 'id', 'gamehub.igdb.client_secret' => 'secret']);
    }

    private function eldenRing(): array
    {
        return [
            'id' => 119133,
            'name' => 'Elden Ring',
            'first_release_date' => 1645747200,
            'summary' => 'An action RPG.',
            'cover' => ['id' => 1, 'image_id' => 'co4jni'],
            'genres' => [['id' => 12, 'name' => 'Role-playing (RPG)']],
        ];
    }

    private function steamGame(string $title = 'ELDEN RING'): Game
    {
        $game = Game::create(['title' => $title]);
        $game->externalIds()->create(['provider' => 'steam', 'external_id' => '1245620']);

        return $game;
    }

    public function test_steam_game_is_enriched_from_igdb(): void
    {
        Http::fake([
            'id.twitch.tv/*' => Http::response(['access_token' => 'tok', 'expires_in' => 5000000]),
            'api.igdb.com/v4/external_games' => Http::response([
                ['id' => 1, 'uid' => '1245620', 'url' => 'https://store.steampowered.com/app/1245620', 'game' => $this->eldenRing()],
            ]),
        ]);

        $game = $this->steamGame();

        EnrichGame::dispatchSync($game->id);

        $game->refresh();
        $this->assertSame(119133, $game->igdb_id);
        $this->assertSame('Elden Ring', $game->title);
        $this->assertStringContainsString('co4jni', $game->cover_url);
        $this->assertSame('2022-02-25', $game->release_date->toDateString());
        $this->assertNotNull($game->enriched_at);
    }

    public function test_same_game_from_two_platforms_is_merged(): void
    {
        Http::fake([
            'id.twitch.tv/*' => Http::response(['access_token' => 'tok', 'expires_in' => 5000000]),
            'api.igdb.com/v4/external_games' => Http::response([
                ['id' => 1, 'uid' => '1245620', 'url' => 'https://store.steampowered.com/app/1245620', 'game' => $this->eldenRing()],
            ]),
        ]);

        $user = User::factory()->admin()->create();
        $ps5 = Platform::where('slug', 'ps5')->firstOrFail();
        $steam = Platform::where('slug', 'steam')->firstOrFail();

        $canonical = Game::create(['igdb_id' => 119133, 'title' => 'Elden Ring']);
        LibraryEntry::create(['user_id' => $user->id, 'game_id' => $canonical->id, 'platform_id' => $ps5->id, 'status' => 'playing', 'playtime_minutes' => 300]);

        $duplicate = $this->steamGame();
        LibraryEntry::create(['user_id' => $user->id, 'game_id' => $duplicate->id, 'platform_id' => $steam->id, 'status' => 'unsorted', 'playtime_minutes' => 600]);

        EnrichGame::dispatchSync($duplicate->id);

        $this->assertDatabaseMissing('games', ['id' => $duplicate->id]);
        $this->assertSame(2, LibraryEntry::where('game_id', $canonical->id)->count());
        $this->assertDatabaseHas('game_external_ids', ['game_id' => $canonical->id, 'provider' => 'steam', 'external_id' => '1245620']);
    }

    public function test_title_search_only_accepts_an_exact_name(): void
    {
        Http::fake([
            'id.twitch.tv/*' => Http::response(['access_token' => 'tok', 'expires_in' => 5000000]),
            'api.igdb.com/v4/games' => Http::response([
                ['id' => 2, 'name' => 'Hades II', 'cover' => ['id' => 9, 'image_id' => 'wrong']],
                ['id' => 1, 'name' => 'Hades', 'cover' => ['id' => 8, 'image_id' => 'right']],
            ]),
        ]);

        $game = Game::create(['title' => 'Hades']);

        EnrichGame::dispatchSync($game->id);

        $this->assertSame(1, $game->fresh()->igdb_id);
        $this->assertStringContainsString('right', $game->fresh()->cover_url);
    }

    public function test_unmatched_game_is_marked_as_attempted(): void
    {
        Http::fake([
            'id.twitch.tv/*' => Http::response(['access_token' => 'tok', 'expires_in' => 5000000]),
            'api.igdb.com/v4/games' => Http::response([['id' => 5, 'name' => 'Something Else Entirely']]),
        ]);

        $game = Game::create(['title' => 'My Homebrew Game']);

        EnrichGame::dispatchSync($game->id);

        $game->refresh();
        $this->assertNull($game->igdb_id);
        $this->assertNotNull($game->enriched_at);
    }
}
