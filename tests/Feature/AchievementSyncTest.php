<?php

namespace Tests\Feature;

use App\Enums\ProviderType;
use App\Models\Game;
use App\Models\LibraryEntry;
use App\Models\LinkedAccount;
use App\Models\Platform;
use App\Models\User;
use App\Services\AchievementSyncService;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class AchievementSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlatformSeeder::class);
        Sleep::fake();
        config(['gamehub.steam.key' => 'KEY']);
    }

    private function account(User $user, ProviderType $type, string $externalId): LinkedAccount
    {
        return LinkedAccount::create([
            'user_id' => $user->id,
            'provider' => $type->value,
            'external_id' => $externalId,
            'display_name' => 'Me',
            'credentials' => $type === ProviderType::Playstation
                ? ['access_token' => 'tok', 'access_expires_at' => now()->addHour()->toIso8601String(), 'refresh_token' => 'r']
                : null,
        ]);
    }

    private function entry(User $user, LinkedAccount $account, string $title, string $platform, ?string $steamAppId = null, int $minutes = 120): LibraryEntry
    {
        $game = Game::create(['title' => $title]);

        if ($steamAppId) {
            $game->externalIds()->create(['provider' => 'steam', 'external_id' => $steamAppId]);
        }

        return LibraryEntry::create([
            'user_id' => $user->id,
            'game_id' => $game->id,
            'platform_id' => Platform::where('slug', $platform)->firstOrFail()->id,
            'linked_account_id' => $account->id,
            'status' => 'playing',
            'playtime_minutes' => $minutes,
            'last_played_at' => now()->subDay(),
        ]);
    }

    public function test_steam_achievements_are_stored_and_games_without_any_are_marked_checked(): void
    {
        $me = User::factory()->admin()->create();
        $account = $this->account($me, ProviderType::Steam, '76561198000000000');
        $hades = $this->entry($me, $account, 'Hades', 'steam', '1145360');
        $video = $this->entry($me, $account, 'Some Video App', 'steam', '999');
        $video->update(['last_played_at' => now()->subDays(5)]); // asked about second

        Http::fake([
            'api.steampowered.com/ISteamUserStats/GetPlayerAchievements/*' => Http::sequence()
                ->push(['playerstats' => ['success' => true, 'achievements' => [['achieved' => 1], ['achieved' => 1], ['achieved' => 0]]]])
                ->push(['playerstats' => ['error' => 'Requested app has no stats', 'success' => false]], 400),
        ]);

        $updated = app(AchievementSyncService::class)->sync($account);

        $this->assertSame(2, $updated);
        $this->assertSame([2, 3], [$hades->fresh()->achievements_unlocked, $hades->fresh()->achievements_total]);
        $this->assertSame(66, $hades->fresh()->achievementPercent());
        $this->assertSame(0, $video->fresh()->achievements_total);
        $this->assertNull($video->fresh()->achievementPercent());
        $this->assertNotNull($video->fresh()->achievements_synced_at);
    }

    public function test_steam_skips_games_already_checked_since_they_were_last_played(): void
    {
        $me = User::factory()->admin()->create();
        $account = $this->account($me, ProviderType::Steam, '76561198000000000');
        $entry = $this->entry($me, $account, 'Hades', 'steam', '1145360');
        $entry->update(['achievements_synced_at' => now()]); // after last_played_at

        Http::fake();

        $this->assertSame(0, app(AchievementSyncService::class)->sync($account));
        Http::assertNothingSent();
    }

    public function test_psn_trophies_match_entries_by_title_and_prefer_the_same_platform(): void
    {
        $me = User::factory()->admin()->create();
        $account = $this->account($me, ProviderType::Playstation, '1234');
        $ps5 = $this->entry($me, $account, 'Returnal', 'ps5');
        $ps4 = $this->entry($me, $account, 'Returnal', 'ps4');
        $other = $this->entry($me, $account, 'Hades', 'ps5');

        Http::fake([
            'm.np.playstation.com/api/trophy/v1/users/me/trophyTitles*' => Http::response(['trophyTitles' => [[
                'trophyTitleName' => 'Returnal Trophies',
                'trophyTitlePlatform' => 'PS5',
                'definedTrophies' => ['bronze' => 30, 'silver' => 5, 'gold' => 2, 'platinum' => 1],
                'earnedTrophies' => ['bronze' => 30, 'silver' => 5, 'gold' => 2, 'platinum' => 1],
            ]]]),
        ]);

        $this->assertSame(1, app(AchievementSyncService::class)->sync($account));

        $this->assertSame(38, $ps5->fresh()->achievements_total);
        $this->assertTrue($ps5->fresh()->hasPlatinum());
        $this->assertNull($ps4->fresh()->achievements_total);
        $this->assertNull($other->fresh()->achievements_total);
    }

    public function test_psn_falls_back_to_the_title_when_the_platform_differs(): void
    {
        $me = User::factory()->admin()->create();
        $account = $this->account($me, ProviderType::Playstation, '1234');
        $entry = $this->entry($me, $account, 'Bloodborne', 'ps5'); // a PS4 game played on a PS5

        Http::fake([
            'm.np.playstation.com/api/trophy/v1/users/me/trophyTitles*' => Http::response(['trophyTitles' => [[
                'trophyTitleName' => 'Bloodborne',
                'trophyTitlePlatform' => 'PS4',
                'definedTrophies' => ['bronze' => 20, 'silver' => 5, 'gold' => 3, 'platinum' => 1],
                'earnedTrophies' => ['bronze' => 5, 'silver' => 0, 'gold' => 0, 'platinum' => 0],
            ]]]),
        ]);

        app(AchievementSyncService::class)->sync($account);

        $this->assertSame([5, 29], [$entry->fresh()->achievements_unlocked, $entry->fresh()->achievements_total]);
        $this->assertFalse($entry->fresh()->hasPlatinum());
    }

    public function test_achievements_show_on_the_library_card(): void
    {
        $me = User::factory()->admin()->create();
        $account = $this->account($me, ProviderType::Steam, '1');
        $entry = $this->entry($me, $account, 'Hades', 'steam', '1145360');
        $entry->update(['achievements_unlocked' => 12, 'achievements_total' => 49]);

        $this->actingAs($me)->get('/games/library')->assertOk()->assertSee('12 of 49 achievements', false);
    }
}
