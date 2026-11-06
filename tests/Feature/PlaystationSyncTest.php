<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Enums\ProviderType;
use App\Models\LibraryEntry;
use App\Models\LinkedAccount;
use App\Models\User;
use App\Services\LibrarySyncService;
use App\Services\Providers\Exceptions\ProviderException;
use App\Services\Providers\PlaystationProvider;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The games fixture is a trimmed copy of a real response (6 titles: 5 PS5, 1 PS4).
 */
class PlaystationSyncTest extends TestCase
{
    use RefreshDatabase;

    private const NPSSO = 'abcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ01';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlatformSeeder::class);

        config(['gamehub.igdb.client_id' => null, 'gamehub.igdb.client_secret' => null]);
    }

    private function games(): array
    {
        return json_decode(file_get_contents(__DIR__.'/../Fixtures/psn-played-games.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    private function tokenResponse(string $access = 'access-1', string $refresh = 'refresh-1'): array
    {
        return ['access_token' => $access, 'refresh_token' => $refresh, 'expires_in' => 3599, 'refresh_token_expires_in' => 5184000];
    }

    private function account(array $credentials): LinkedAccount
    {
        return LinkedAccount::create([
            'user_id' => User::factory()->admin()->create()->id,
            'provider' => ProviderType::Playstation,
            'external_id' => '4242424242424242',
            'display_name' => 'tester',
            'credentials' => $credentials,
        ]);
    }

    private function validCredentials(): array
    {
        return [
            'access_token' => 'live-token',
            'access_expires_at' => now()->addHour()->toIso8601String(),
            'refresh_token' => 'live-refresh',
            'refresh_expires_at' => now()->addDays(30)->toIso8601String(),
        ];
    }

    private function entryFor(string $title): LibraryEntry
    {
        return LibraryEntry::whereHas('game', fn ($q) => $q->where('title', $title))->with('platform', 'game')->firstOrFail();
    }

    public function test_link_exchanges_npsso_and_stores_tokens_but_never_the_npsso(): void
    {
        Http::fake([
            'ca.account.sony.com/api/authz/v3/oauth/authorize*' => Http::response('', 302, [
                'Location' => 'com.scee.psxandroid.scecompcall://redirect/?code=v3.thecode&cid=abc',
            ]),
            'ca.account.sony.com/api/authz/v3/oauth/token' => Http::response($this->tokenResponse()),
            'm.np.playstation.com/api/trophy/*' => Http::response(['accountId' => '4242424242424242', 'trophyLevel' => '12']),
            'm.np.playstation.com/api/userProfile/*' => Http::response(['onlineId' => 'NikoPlays']),
        ]);

        $resolved = app(PlaystationProvider::class)->resolveAccount(self::NPSSO);

        $this->assertSame('4242424242424242', $resolved->externalId);
        $this->assertSame('NikoPlays', $resolved->displayName);
        $this->assertSame('access-1', $resolved->credentials['access_token']);
        $this->assertSame('refresh-1', $resolved->credentials['refresh_token']);
        $this->assertStringNotContainsString(self::NPSSO, json_encode($resolved->credentials));

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/oauth/authorize')
            && $r->header('Cookie') === ['npsso='.self::NPSSO]);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/oauth/token')
            && str_contains($r->body(), 'code=v3.thecode')
            && str_contains($r->body(), 'grant_type=authorization_code'));
    }

    public function test_pasting_the_whole_json_page_works(): void
    {
        Http::fake([
            'ca.account.sony.com/api/authz/v3/oauth/authorize*' => Http::response('', 302, [
                'Location' => 'com.scee.psxandroid.scecompcall://redirect/?code=v3.x',
            ]),
            'ca.account.sony.com/api/authz/v3/oauth/token' => Http::response($this->tokenResponse()),
            'm.np.playstation.com/api/trophy/*' => Http::response(['accountId' => '1']),
            'm.np.playstation.com/api/userProfile/*' => Http::response([], 500),
        ]);

        $resolved = app(PlaystationProvider::class)->resolveAccount('{"npsso":"'.self::NPSSO.'"}');

        $this->assertSame('1', $resolved->externalId);
        $this->assertSame('PlayStation account', $resolved->displayName, 'a failing profile call must not fail the link');
        Http::assertSent(fn (Request $r) => $r->header('Cookie') === ['npsso='.self::NPSSO]);
    }

    public function test_an_invalid_npsso_is_rejected_with_instructions(): void
    {
        Http::fake(['ca.account.sony.com/api/authz/v3/oauth/authorize*' => Http::response('', 302, [
            'Location' => 'com.scee.psxandroid.scecompcall://redirect/?error=login_required',
        ])]);

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('did not accept that NPSSO');

        app(PlaystationProvider::class)->resolveAccount(self::NPSSO);
    }

    public function test_something_that_is_not_a_token_never_reaches_playstation(): void
    {
        Http::fake();

        try {
            app(PlaystationProvider::class)->resolveAccount('hello world');
            $this->fail('expected a ProviderException');
        } catch (ProviderException $e) {
            $this->assertStringContainsString('does not look like an NPSSO', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_sync_imports_games_with_platform_playtime_and_cover(): void
    {
        Http::fake(['m.np.playstation.com/api/gamelist/*' => Http::response($this->games())]);

        $run = app(LibrarySyncService::class)->sync($this->account($this->validCredentials()));

        $this->assertSame('success', $run->status, (string) $run->error);
        $this->assertSame(6, $run->games_seen);
        $this->assertSame(6, $run->created_count);
        $this->assertDatabaseCount('library_entries', 6);

        $alan = $this->entryFor('Alan Wake 2');
        $this->assertSame('ps5', $alan->platform->slug);
        $this->assertSame(221, $alan->playtime_minutes);          // PT3H40M52S
        $this->assertSame(GameStatus::Unsorted, $alan->status);
        $this->assertSame('playstation', $alan->source);
        $this->assertSame('2026-10-27', $alan->last_played_at->toDateString());
        $this->assertStringContainsString('2b117db6', $alan->game->cover_url); // GAMEHUB_COVER_ART

        $rdr2 = $this->entryFor('Red Dead Redemption 2');
        $this->assertSame('ps4', $rdr2->platform->slug);          // category ps4_game
        $this->assertSame(3839, $rdr2->playtime_minutes);         // PT63H58M50S, hours above 24

        $this->assertSame(2, $this->entryFor('EA SPORTS FC™ 26')->playtime_minutes); // PT1M52S

        // The PlayStation concept id is what ties PS4 and PS5 versions to one game.
        $this->assertDatabaseHas('game_external_ids', ['provider' => 'playstation', 'external_id' => '10002065']);
    }

    public function test_resync_updates_playtime_and_keeps_user_status(): void
    {
        $second = $this->games();
        $second['titles'][0]['playDuration'] = 'PT5H0M0S';   // Alan Wake 2: 221 -> 300 minutes
        $second['titles'][3]['playDuration'] = 'PT15H45M46S'; // unchanged

        Http::fake(['m.np.playstation.com/api/gamelist/*' => Http::sequence()
            ->push($this->games())
            ->push($second)]);

        $account = $this->account($this->validCredentials());
        $sync = app(LibrarySyncService::class);
        $sync->sync($account);

        $this->entryFor('Alan Wake 2')->applyStatus(GameStatus::Finished);

        $run = $sync->sync($account);

        $this->assertSame(0, $run->created_count);
        $this->assertDatabaseCount('library_entries', 6);
        $this->assertSame(300, $this->entryFor('Alan Wake 2')->playtime_minutes);
        $this->assertSame(GameStatus::Finished, $this->entryFor('Alan Wake 2')->status);
    }

    public function test_pagination_follows_next_offset(): void
    {
        $all = $this->games();
        $first = $all;
        $first['titles'] = array_slice($all['titles'], 0, 3);
        $first['nextOffset'] = 3;
        $second = $all;
        $second['titles'] = array_slice($all['titles'], 3);
        $second['nextOffset'] = null;

        Http::fake(['m.np.playstation.com/api/gamelist/*' => Http::sequence()->push($first)->push($second)]);

        $run = app(LibrarySyncService::class)->sync($this->account($this->validCredentials()));

        $this->assertSame(6, $run->games_seen);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'offset=3'));
    }

    public function test_expired_access_token_is_renewed_with_the_refresh_token(): void
    {
        Http::fake([
            'ca.account.sony.com/api/authz/v3/oauth/token' => Http::response($this->tokenResponse('access-2', 'refresh-2')),
            'm.np.playstation.com/api/gamelist/*' => Http::response($this->games()),
        ]);

        $account = $this->account([
            'access_token' => 'old-token',
            'access_expires_at' => now()->subMinute()->toIso8601String(),
            'refresh_token' => 'refresh-1',
            'refresh_expires_at' => now()->addDays(30)->toIso8601String(),
        ]);

        $run = app(LibrarySyncService::class)->sync($account);

        $this->assertSame('success', $run->status, (string) $run->error);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/oauth/token')
            && str_contains($r->body(), 'grant_type=refresh_token')
            && str_contains($r->body(), 'refresh_token=refresh-1'));
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/gamelist/')
            && $r->header('Authorization') === ['Bearer access-2']);

        $stored = $account->fresh()->credentials;
        $this->assertSame('access-2', $stored['access_token']);
        $this->assertSame('refresh-2', $stored['refresh_token']);
    }

    public function test_a_401_from_the_games_api_triggers_one_renewal_and_retry(): void
    {
        Http::fake([
            'ca.account.sony.com/api/authz/v3/oauth/token' => Http::response($this->tokenResponse('access-3')),
            'm.np.playstation.com/api/gamelist/*' => Http::sequence()
                ->push(['error' => ['message' => 'expired']], 401)
                ->push($this->games()),
        ]);

        $run = app(LibrarySyncService::class)->sync($this->account($this->validCredentials()));

        $this->assertSame('success', $run->status, (string) $run->error);
        $this->assertSame(6, $run->games_seen);
    }

    public function test_dead_refresh_token_fails_the_run_with_a_relogin_message(): void
    {
        Http::fake(['ca.account.sony.com/api/authz/v3/oauth/token' => Http::response(['error' => 'invalid_grant'], 400)]);

        $account = $this->account([
            'access_token' => 'old',
            'access_expires_at' => now()->subHour()->toIso8601String(),
            'refresh_token' => 'dead',
            'refresh_expires_at' => now()->addDays(1)->toIso8601String(),
        ]);

        $run = app(LibrarySyncService::class)->sync($account);

        $this->assertSame('failed', $run->status);
        $this->assertStringContainsString('Paste a fresh NPSSO', $run->error);
        $this->assertSame('error', $account->fresh()->last_status);
        $this->assertDatabaseCount('library_entries', 0);
    }

    public function test_an_expired_refresh_token_does_not_even_call_playstation(): void
    {
        Http::fake();

        $account = $this->account([
            'access_token' => 'old',
            'access_expires_at' => now()->subHour()->toIso8601String(),
            'refresh_token' => 'stale',
            'refresh_expires_at' => now()->subDay()->toIso8601String(),
        ]);

        $run = app(LibrarySyncService::class)->sync($account);

        $this->assertSame('failed', $run->status);
        Http::assertNothingSent();
    }
}
