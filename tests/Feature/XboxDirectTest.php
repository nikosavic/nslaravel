<?php

namespace Tests\Feature;

use App\Enums\ProviderType;
use App\Livewire\Accounts;
use App\Models\LibraryEntry;
use App\Models\LinkedAccount;
use App\Models\User;
use App\Services\AchievementSyncService;
use App\Services\LibrarySyncService;
use App\Services\Providers\Exceptions\ProviderException;
use App\Services\Providers\Xbox\XboxLiveClient;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class XboxDirectTest extends TestCase
{
    use RefreshDatabase;

    private const DEVICE = 'login.microsoftonline.com/consumers/oauth2/v2.0/devicecode';
    private const TOKEN = 'login.microsoftonline.com/consumers/oauth2/v2.0/token';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlatformSeeder::class);
        config(['gamehub.xbox.client_id' => 'client-123', 'gamehub.igdb.client_id' => null, 'gamehub.igdb.client_secret' => null]);
    }

    private function msTokens(string $refresh = 'refresh-1'): array
    {
        return ['access_token' => 'ms-access', 'refresh_token' => $refresh, 'expires_in' => 3600];
    }

    private function xblFakes(): array
    {
        return [
            'user.auth.xboxlive.com/*' => Http::response(['Token' => 'user-token', 'DisplayClaims' => ['xui' => [['uhs' => 'hash']]]]),
            'xsts.auth.xboxlive.com/*' => Http::response([
                'Token' => 'xsts-token',
                'NotAfter' => now()->addHours(16)->toIso8601String(),
                'DisplayClaims' => ['xui' => [['uhs' => 'hash', 'xid' => '2533274800000000', 'gtg' => 'NikoPlays']]],
            ]),
        ];
    }

    private function titleHistory(): array
    {
        return ['titles' => [
            [
                'titleId' => '1001', 'name' => 'Forza Horizon 5', 'type' => 'Game', 'devices' => ['XboxSeries', 'XboxOne'],
                'titleHistory' => ['lastTimePlayed' => '2026-10-01T18:30:00Z'],
                'achievement' => ['currentAchievements' => 40, 'totalAchievements' => 120],
            ],
            ['titleId' => '1002', 'name' => 'Netflix', 'type' => 'App', 'devices' => ['XboxSeries']],
        ]];
    }

    private function directAccount(User $user, array $xsts = null): LinkedAccount
    {
        return LinkedAccount::create([
            'user_id' => $user->id,
            'provider' => ProviderType::Xbox->value,
            'external_id' => '2533274800000000',
            'display_name' => 'NikoPlays',
            'credentials' => [
                'mode' => 'direct',
                'refresh_token' => 'refresh-1',
                'xsts' => $xsts ?? ['token' => 'xsts-token', 'uhs' => 'hash', 'xuid' => '2533274800000000', 'gamertag' => 'NikoPlays', 'expires_at' => now()->addHours(10)->toIso8601String()],
            ],
        ]);
    }

    public function test_starting_a_device_login_returns_the_code_and_needs_a_client_id(): void
    {
        Http::fake([self::DEVICE => Http::response(['device_code' => 'dc', 'user_code' => 'ABCD1234', 'verification_uri' => 'https://www.microsoft.com/link', 'expires_in' => 900, 'interval' => 5])]);

        $login = app(XboxLiveClient::class)->startDeviceLogin();

        $this->assertSame('ABCD1234', $login['user_code']);
        Http::assertSent(fn ($r) => $r['client_id'] === 'client-123' && str_contains($r['scope'], 'XboxLive.signin') && str_contains($r['scope'], 'offline_access'));

        config(['gamehub.xbox.client_id' => null]);
        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('XBOX_CLIENT_ID');
        app(XboxLiveClient::class)->startDeviceLogin();
    }

    public function test_polling_maps_microsofts_answers(): void
    {
        Http::fake([self::TOKEN => Http::sequence()
            ->push(['error' => 'authorization_pending'], 400)
            ->push(['error' => 'slow_down'], 400)
            ->push(['error' => 'authorization_declined'], 400)
            ->push(['error' => 'expired_token'], 400)
            ->push($this->msTokens())]);

        $client = app(XboxLiveClient::class);

        $this->assertSame('pending', $client->pollDeviceLogin('dc')['status']);
        $this->assertSame('slow_down', $client->pollDeviceLogin('dc')['status']);
        $this->assertSame('declined', $client->pollDeviceLogin('dc')['status']);
        $this->assertSame('expired', $client->pollDeviceLogin('dc')['status']);

        $ok = $client->pollDeviceLogin('dc');
        $this->assertSame('ok', $ok['status']);
        $this->assertSame('refresh-1', $ok['tokens']['refresh_token']);
    }

    public function test_the_identity_chain_sends_the_rps_ticket_and_reads_xuid_and_gamertag(): void
    {
        Http::fake($this->xblFakes());

        $identity = app(XboxLiveClient::class)->identity('ms-access');

        $this->assertSame(['xsts-token', 'hash', '2533274800000000', 'NikoPlays'], [$identity['token'], $identity['uhs'], $identity['xuid'], $identity['gamertag']]);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'user.auth') && $r['Properties']['RpsTicket'] === 'd=ms-access');
        Http::assertSent(fn ($r) => str_contains($r->url(), 'xsts') && $r['Properties']['UserTokens'] === ['user-token'] && $r['RelyingParty'] === 'http://xboxlive.com');
    }

    public function test_xbox_error_codes_become_plain_messages(): void
    {
        Http::fake(['user.auth.xboxlive.com/*' => Http::response(['Token' => 'u', 'DisplayClaims' => ['xui' => [['uhs' => 'h']]]]),
            'xsts.auth.xboxlive.com/*' => Http::response(['XErr' => 2148916233], 401)]);

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('no Xbox profile yet');

        app(XboxLiveClient::class)->identity('ms-access');
    }

    public function test_a_valid_session_is_reused_and_an_expired_one_is_renewed_with_the_rotated_refresh_token(): void
    {
        $me = User::factory()->admin()->create();
        $fresh = $this->directAccount($me);

        Http::fake();
        $this->assertSame('xsts-token', app(XboxLiveClient::class)->session($fresh)['token']);
        Http::assertNothingSent();

        $stale = $this->directAccount(User::factory()->admin()->create(), ['token' => 'old', 'uhs' => 'h', 'xuid' => '1', 'gamertag' => null, 'expires_at' => now()->subHour()->toIso8601String()]);

        Http::fake([self::TOKEN => Http::response($this->msTokens('refresh-2'))] + $this->xblFakes());

        $session = app(XboxLiveClient::class)->session($stale);

        $this->assertSame('xsts-token', $session['token']);
        $this->assertSame('refresh-2', $stale->fresh()->credentials['refresh_token']);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'oauth2/v2.0/token') && $r['grant_type'] === 'refresh_token' && $r['refresh_token'] === 'refresh-1');
    }

    public function test_a_revoked_refresh_token_asks_you_to_link_again(): void
    {
        $account = $this->directAccount(User::factory()->admin()->create(), ['token' => 'old', 'uhs' => 'h', 'xuid' => '1', 'gamertag' => null, 'expires_at' => now()->subHour()->toIso8601String()]);

        Http::fake([self::TOKEN => Http::response(['error' => 'invalid_grant'], 400)]);

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Link Xbox again');

        app(XboxLiveClient::class)->session($account);
    }

    public function test_a_direct_sync_imports_games_apps_are_skipped_and_hours_are_read_when_reported(): void
    {
        $me = User::factory()->admin()->create();
        $account = $this->directAccount($me);

        Http::fake([
            'titlehub.xboxlive.com/*' => Http::response($this->titleHistory()),
            'userstats.xboxlive.com/*' => Http::response(['statlistscollection' => [['stats' => [
                ['name' => 'MinutesPlayed', 'titleid' => '1001', 'value' => '600'],
            ]]]]),
        ]);

        $run = app(LibrarySyncService::class)->sync($account);

        $this->assertSame('success', $run->status);
        $this->assertSame(1, LibraryEntry::count());

        $entry = LibraryEntry::firstOrFail();
        $this->assertSame(600, $entry->playtime_minutes);
        $this->assertSame('xbox-series', $entry->platform->slug);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'titlehub') && str_contains($r->header('Authorization')[0] ?? '', 'XBL3.0 x=hash;xsts-token'));

        app(AchievementSyncService::class)->sync($account);
        $this->assertSame([40, 120], [$entry->fresh()->achievements_unlocked, $entry->fresh()->achievements_total]);
    }

    public function test_hours_are_optional_a_failing_stats_call_never_fails_the_sync(): void
    {
        $me = User::factory()->admin()->create();
        $account = $this->directAccount($me);

        Http::fake([
            'titlehub.xboxlive.com/*' => Http::response($this->titleHistory()),
            'userstats.xboxlive.com/*' => Http::response('nope', 500),
        ]);

        $run = app(LibrarySyncService::class)->sync($account);

        $this->assertSame('success', $run->status);
        $this->assertSame(0, LibraryEntry::firstOrFail()->playtime_minutes);
    }

    public function test_a_401_from_xbox_live_renews_the_token_once_and_retries(): void
    {
        $me = User::factory()->admin()->create();
        $account = $this->directAccount($me);

        Http::fake([
            'titlehub.xboxlive.com/*' => Http::sequence()->push('', 401)->push($this->titleHistory()),
            'userstats.xboxlive.com/*' => Http::response(['statlistscollection' => []]),
            self::TOKEN => Http::response($this->msTokens('refresh-2')),
        ] + $this->xblFakes());

        $run = app(LibrarySyncService::class)->sync($account);

        $this->assertSame('success', $run->status);
        $this->assertSame(1, LibraryEntry::count());
        $this->assertSame('refresh-2', $account->fresh()->credentials['refresh_token']);
    }

    public function test_the_accounts_page_runs_the_whole_device_login(): void
    {
        Queue::fake();
        $me = User::factory()->admin()->create();

        Http::fake([
            self::DEVICE => Http::response(['device_code' => 'dc', 'user_code' => 'ABCD1234', 'verification_uri' => 'https://www.microsoft.com/link', 'expires_in' => 900, 'interval' => 5]),
            self::TOKEN => Http::sequence()->push(['error' => 'authorization_pending'], 400)->push($this->msTokens()),
        ] + $this->xblFakes());

        $page = Livewire::actingAs($me)->test(Accounts::class)
            ->call('startXboxLogin')
            ->assertSet('xboxCode', 'ABCD1234')
            ->assertSee('ABCD1234');

        // Too soon: Microsoft's polling interval is respected, nothing is asked.
        $page->call('checkXboxLogin');
        $this->assertSame(0, LinkedAccount::count());

        $this->travel(6)->seconds();
        $page->call('checkXboxLogin')->assertSet('xboxCode', 'ABCD1234'); // still pending

        $this->travel(6)->seconds();
        $page->call('checkXboxLogin')->assertSet('xboxCode', null);

        $account = LinkedAccount::firstOrFail();
        $this->assertSame('NikoPlays', $account->display_name);
        $this->assertSame('refresh-1', $account->credentials['refresh_token']);
        $this->assertStringNotContainsString('refresh-1', (string) \DB::table('linked_accounts')->value('credentials'));
    }

    public function test_without_a_client_id_the_page_explains_the_azure_setup(): void
    {
        config(['gamehub.xbox.client_id' => null]);

        Livewire::actingAs(User::factory()->admin()->create())->test(Accounts::class)
            ->assertSee('App registrations')
            ->assertSee('XBOX_CLIENT_ID')
            ->assertDontSee('Link with Microsoft');
    }

    public function test_a_declined_login_shows_a_message_and_clears_the_code(): void
    {
        $me = User::factory()->admin()->create();

        Http::fake([
            self::DEVICE => Http::response(['device_code' => 'dc', 'user_code' => 'ABCD1234', 'expires_in' => 900, 'interval' => 5]),
            self::TOKEN => Http::response(['error' => 'authorization_declined'], 400),
        ]);

        $page = Livewire::actingAs($me)->test(Accounts::class)->call('startXboxLogin');
        $this->travel(6)->seconds();
        $page->call('checkXboxLogin')->assertSet('xboxCode', null)->assertHasErrors('xboxLogin');
    }
}
