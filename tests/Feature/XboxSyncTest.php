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
use App\Services\Providers\XboxProvider;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class XboxSyncTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'abcd1234abcd1234abcd1234abcd1234';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlatformSeeder::class);
        config(['gamehub.igdb.client_id' => null, 'gamehub.igdb.client_secret' => null]);
    }

    private function titles(): array
    {
        return ['titles' => [
            [
                'titleId' => '1001', 'name' => 'Forza Horizon 5', 'devices' => ['XboxSeries', 'XboxOne'],
                'titleHistory' => ['lastTimePlayed' => '2026-10-01T18:30:00Z'],
                'achievement' => ['currentAchievements' => 40, 'totalAchievements' => 120],
            ],
            [
                'titleId' => '1002', 'name' => 'Halo Infinite', 'devices' => ['XboxOne'],
                'achievement' => ['currentAchievements' => 0, 'totalAchievements' => 0],
            ],
            ['titleId' => '1003', 'name' => 'PC thing', 'devices' => ['PC']],
        ]];
    }

    private function accountResponse(): array
    {
        return ['profileUsers' => [['id' => '2533274800000000', 'settings' => [['id' => 'Gamertag', 'value' => 'NikoPlays']]]]];
    }

    public function test_resolving_an_account_reads_the_xuid_and_gamertag_and_keeps_the_key(): void
    {
        Http::fake(['api.xbl.io/v2/account' => Http::response($this->accountResponse())]);

        $resolved = app(XboxProvider::class)->resolveAccount('  '.self::KEY.' ');

        $this->assertSame('2533274800000000', $resolved->externalId);
        $this->assertSame('NikoPlays', $resolved->displayName);
        $this->assertSame(['api_key' => self::KEY], $resolved->credentials);

        Http::assertSent(fn ($request) => $request->hasHeader('X-Authorization', self::KEY));
    }

    public function test_bad_or_rejected_keys_give_a_clear_message(): void
    {
        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('does not look like an OpenXBL API key');

        app(XboxProvider::class)->resolveAccount('nope');
    }

    public function test_a_401_from_openxbl_means_the_key_was_rejected(): void
    {
        Http::fake(['api.xbl.io/*' => Http::response(['error' => 'bad key'], 401)]);

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('did not accept that API key');

        app(XboxProvider::class)->resolveAccount(self::KEY);
    }

    public function test_sync_imports_xbox_titles_and_achievements(): void
    {
        $me = User::factory()->admin()->create();
        $account = LinkedAccount::create([
            'user_id' => $me->id,
            'provider' => ProviderType::Xbox->value,
            'external_id' => '2533274800000000',
            'display_name' => 'NikoPlays',
            'credentials' => ['api_key' => self::KEY],
        ]);

        Http::fake(['api.xbl.io/v2/achievements' => Http::response($this->titles())]);

        $run = app(LibrarySyncService::class)->sync($account);

        $this->assertSame('success', $run->status);
        $this->assertSame(2, LibraryEntry::count()); // the PC-only title is skipped

        $forza = LibraryEntry::whereHas('game', fn ($q) => $q->where('title', 'Forza Horizon 5'))->firstOrFail();
        $this->assertSame('xbox-series', $forza->platform->slug);
        $this->assertSame(0, $forza->playtime_minutes);
        $this->assertSame('2026-10-01', $forza->last_played_at->toDateString());

        app(AchievementSyncService::class)->sync($account);

        $this->assertSame([40, 120], [$forza->fresh()->achievements_unlocked, $forza->fresh()->achievements_total]);
        $halo = LibraryEntry::whereHas('game', fn ($q) => $q->where('title', 'Halo Infinite'))->firstOrFail();
        $this->assertNull($halo->achievements_total); // no achievements reported: nothing stored
    }

    public function test_a_newer_last_played_date_promotes_a_backlog_game_even_without_hours(): void
    {
        $me = User::factory()->admin()->create();
        $account = LinkedAccount::create([
            'user_id' => $me->id,
            'provider' => ProviderType::Xbox->value,
            'external_id' => '1',
            'credentials' => ['api_key' => self::KEY],
        ]);

        $first = $this->titles();
        $first['titles'][0]['titleHistory']['lastTimePlayed'] = '2026-09-01T10:00:00Z';
        Http::fake(['api.xbl.io/v2/achievements' => Http::sequence()->push($first)->push($this->titles())]);

        $sync = app(LibrarySyncService::class);
        $sync->sync($account);

        $forza = LibraryEntry::whereHas('game', fn ($q) => $q->where('title', 'Forza Horizon 5'))->firstOrFail();
        $forza->update(['status' => 'backlog']);

        $sync->sync($account);

        $this->assertSame('playing', $forza->fresh()->status->value);
    }

    public function test_linking_xbox_from_the_accounts_page_stores_the_key_encrypted_and_queues_a_sync(): void
    {
        Queue::fake();
        Http::fake(['api.xbl.io/v2/account' => Http::response($this->accountResponse())]);
        $me = User::factory()->admin()->create();

        Livewire::actingAs($me)->test(Accounts::class)
            ->set('xboxInput', self::KEY)
            ->call('linkXbox')
            ->assertHasNoErrors()
            ->assertSet('xboxInput', '');

        $account = LinkedAccount::firstOrFail();
        $this->assertSame('NikoPlays', $account->display_name);
        $this->assertSame(self::KEY, $account->credentials['api_key']);
        $this->assertStringNotContainsString(self::KEY, (string) \DB::table('linked_accounts')->value('credentials'));
    }

    public function test_a_bad_key_shows_an_error_on_the_form(): void
    {
        $me = User::factory()->admin()->create();

        Livewire::actingAs($me)->test(Accounts::class)
            ->set('xboxInput', 'short')
            ->call('linkXbox')
            ->assertHasErrors('xboxInput');

        $this->assertSame(0, LinkedAccount::count());
    }
}
