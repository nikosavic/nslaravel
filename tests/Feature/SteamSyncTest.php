<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Enums\ProviderType;
use App\Models\LibraryEntry;
use App\Models\LinkedAccount;
use App\Models\User;
use App\Services\LibrarySyncService;
use App\Services\Providers\SteamProvider;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SteamSyncTest extends TestCase
{
    use RefreshDatabase;

    private LinkedAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlatformSeeder::class);

        config([
            'gamehub.steam.key' => 'test-key',
            'gamehub.igdb.client_id' => null,
            'gamehub.igdb.client_secret' => null,
            'gamehub.auto_promote' => true,
        ]);

        $this->account = LinkedAccount::create([
            'user_id' => User::factory()->admin()->create()->id,
            'provider' => ProviderType::Steam,
            'external_id' => '76561198000000000',
            'display_name' => 'tester',
        ]);
    }

    private function owned(int $eldenMinutes, int $hadesMinutes): array
    {
        return ['response' => ['game_count' => 2, 'games' => [
            ['appid' => 1245620, 'name' => 'ELDEN RING', 'playtime_forever' => $eldenMinutes, 'rtime_last_played' => 1760000000],
            ['appid' => 1145360, 'name' => 'Hades', 'playtime_forever' => $hadesMinutes],
        ]]];
    }

    private function entryFor(string $title): LibraryEntry
    {
        return LibraryEntry::whereHas('game', fn ($q) => $q->where('title', $title))->firstOrFail();
    }

    public function test_first_sync_imports_games_as_unsorted(): void
    {
        Http::fake(['api.steampowered.com/IPlayerService/GetOwnedGames/*' => Http::response($this->owned(600, 0))]);

        $run = app(LibrarySyncService::class)->sync($this->account);

        $this->assertSame('success', $run->status);
        $this->assertSame(2, $run->games_seen);
        $this->assertSame(2, $run->created_count);
        $this->assertDatabaseCount('library_entries', 2);
        $this->assertDatabaseHas('game_external_ids', ['provider' => 'steam', 'external_id' => '1245620']);

        $elden = $this->entryFor('ELDEN RING');
        $this->assertSame(GameStatus::Unsorted, $elden->status);
        $this->assertSame(600, $elden->playtime_minutes);
        $this->assertNotNull($elden->last_played_at);
        $this->assertSame('steam', $elden->source);
        $this->assertSame('success', $this->account->fresh()->latestRun->status);
        $this->assertSame('ok', $this->account->fresh()->last_status);
    }

    public function test_resync_updates_playtime_but_never_overwrites_user_status(): void
    {
        Http::fake(['api.steampowered.com/IPlayerService/GetOwnedGames/*' => Http::sequence()
            ->push($this->owned(600, 0))
            ->push($this->owned(900, 120)),
        ]);

        $sync = app(LibrarySyncService::class);
        $sync->sync($this->account);

        $this->entryFor('ELDEN RING')->applyStatus(GameStatus::Finished);
        $this->entryFor('Hades')->applyStatus(GameStatus::Backlog);

        $run = $sync->sync($this->account);

        $this->assertSame(0, $run->created_count);
        $this->assertSame(2, $run->updated_count);
        $this->assertDatabaseCount('library_entries', 2);

        $elden = $this->entryFor('ELDEN RING');
        $this->assertSame(GameStatus::Finished, $elden->status, 'user-set status must survive a sync');
        $this->assertSame(900, $elden->playtime_minutes);

        $hades = $this->entryFor('Hades');
        $this->assertSame(GameStatus::Playing, $hades->status, 'new playtime on a backlog game flips it to Playing');
        $this->assertNotNull($hades->started_at);
    }

    public function test_auto_promote_can_be_disabled(): void
    {
        config(['gamehub.auto_promote' => false]);

        Http::fake(['api.steampowered.com/IPlayerService/GetOwnedGames/*' => Http::sequence()
            ->push($this->owned(0, 0))
            ->push($this->owned(0, 120)),
        ]);

        $sync = app(LibrarySyncService::class);
        $sync->sync($this->account);
        $this->entryFor('Hades')->applyStatus(GameStatus::Backlog);
        $sync->sync($this->account);

        $this->assertSame(GameStatus::Backlog, $this->entryFor('Hades')->status);
    }

    public function test_private_profile_is_recorded_as_a_failed_run_not_an_exception(): void
    {
        Http::fake(['api.steampowered.com/*' => Http::response(['response' => []])]);

        $run = app(LibrarySyncService::class)->sync($this->account);

        $this->assertSame('failed', $run->status);
        $this->assertStringContainsString('Game details', $run->error);
        $this->assertSame('error', $this->account->fresh()->last_status);
        $this->assertDatabaseCount('library_entries', 0);
    }

    public function test_missing_api_key_fails_without_calling_steam(): void
    {
        config(['gamehub.steam.key' => null]);
        Http::fake();

        $run = app(LibrarySyncService::class)->sync($this->account);

        $this->assertSame('failed', $run->status);
        $this->assertStringContainsString('STEAM_API_KEY', $run->error);
        Http::assertNothingSent();
    }

    public function test_profile_url_is_resolved_to_a_steam_id(): void
    {
        Http::fake([
            'api.steampowered.com/ISteamUser/ResolveVanityURL/*' => Http::response(['response' => ['success' => 1, 'steamid' => '76561197960287930']]),
            'api.steampowered.com/ISteamUser/GetPlayerSummaries/*' => Http::response(['response' => ['players' => [
                ['steamid' => '76561197960287930', 'personaname' => 'Rabscuttle', 'communitystatevisibilitystate' => 3],
            ]]]),
        ]);

        $resolved = app(SteamProvider::class)->resolveAccount('https://steamcommunity.com/id/gabelogannewell/');

        $this->assertSame('76561197960287930', $resolved->externalId);
        $this->assertSame('Rabscuttle', $resolved->displayName);
        $this->assertNull($resolved->warning);
    }

    public function test_non_public_profile_returns_a_warning(): void
    {
        Http::fake([
            'api.steampowered.com/ISteamUser/GetPlayerSummaries/*' => Http::response(['response' => ['players' => [
                ['steamid' => '76561198000000000', 'personaname' => 'Hidden', 'communitystatevisibilitystate' => 1],
            ]]]),
        ]);

        $resolved = app(SteamProvider::class)->resolveAccount('76561198000000000');

        $this->assertNotNull($resolved->warning);
    }
}
