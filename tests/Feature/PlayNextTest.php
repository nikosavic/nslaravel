<?php

namespace Tests\Feature;

use App\Livewire\PlayNext;
use App\Models\Game;
use App\Models\LibraryEntry;
use App\Models\Platform;
use App\Models\User;
use App\Services\Igdb\IgdbClient;
use App\Services\NextGamePicker;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class PlayNextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlatformSeeder::class);
    }

    private function entry(User $user, string $title, string $status, ?int $beatMinutes = null, int $played = 0, array $extra = []): LibraryEntry
    {
        return LibraryEntry::create($extra + [
            'user_id' => $user->id,
            'game_id' => Game::create(['title' => $title, 'ttb_main_minutes' => $beatMinutes])->id,
            'platform_id' => Platform::where('slug', 'steam')->firstOrFail()->id,
            'status' => $status,
            'playtime_minutes' => $played,
        ]);
    }

    public function test_a_game_close_to_the_end_beats_a_fresh_one(): void
    {
        $me = User::factory()->admin()->create();
        $this->entry($me, 'Fresh', 'backlog', 600);
        $this->entry($me, 'Nearly done', 'playing', 600, 540);

        $picks = app(NextGamePicker::class)->suggest($me->id);

        $this->assertSame('Nearly done', $picks[0]['entry']->game->title);
        $this->assertContains("You're already playing it", $picks[0]['reasons']);
    }

    public function test_modes_split_started_from_new_games(): void
    {
        $me = User::factory()->admin()->create();
        $this->entry($me, 'Started', 'playing');
        $this->entry($me, 'Paused', 'on_hold');
        $this->entry($me, 'Waiting', 'backlog');

        $picker = app(NextGamePicker::class);

        $continue = collect($picker->suggest($me->id, 'continue', limit: 10))->map(fn ($p) => $p['entry']->game->title)->all();
        $new = collect($picker->suggest($me->id, 'new', limit: 10))->map(fn ($p) => $p['entry']->game->title)->all();

        $this->assertEqualsCanonicalizing(['Started', 'Paused'], $continue);
        $this->assertSame(['Waiting'], $new);
    }

    public function test_length_filter_only_excludes_games_with_a_known_length(): void
    {
        $me = User::factory()->admin()->create();
        $this->entry($me, 'Short', 'backlog', 5 * 60);
        $this->entry($me, 'Epic', 'backlog', 80 * 60);
        $this->entry($me, 'Unknown length', 'backlog');

        $titles = collect(app(NextGamePicker::class)->suggest($me->id, 'any', 'short', limit: 10))
            ->map(fn ($p) => $p['entry']->game->title)->all();

        $this->assertEqualsCanonicalizing(['Short', 'Unknown length'], $titles);
    }

    public function test_finished_and_wishlist_games_and_other_users_are_never_suggested(): void
    {
        $me = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();

        $this->entry($me, 'Done', 'finished');
        $this->entry($me, 'Wanted', 'wishlist');
        $this->entry($other, 'Theirs', 'playing');

        $this->assertSame([], app(NextGamePicker::class)->suggest($me->id));
    }

    public function test_the_same_seed_gives_the_same_order(): void
    {
        $me = User::factory()->admin()->create();

        foreach (range(1, 6) as $i) {
            $this->entry($me, "Game {$i}", 'backlog');
        }

        $picker = app(NextGamePicker::class);
        $titles = fn (int $seed) => collect($picker->suggest($me->id, seed: $seed, limit: 6))->map(fn ($p) => $p['entry']->game->title)->all();

        $this->assertSame($titles(7), $titles(7));
    }

    public function test_backlog_summary_counts_hours_left_and_unknowns(): void
    {
        $me = User::factory()->admin()->create();
        $this->entry($me, 'A', 'backlog', 600);
        $this->entry($me, 'B', 'playing', 600, 240);
        $this->entry($me, 'C', 'on_hold');
        $this->entry($me, 'Finished', 'finished', 6000);

        $summary = app(NextGamePicker::class)->backlogSummary($me->id);

        $this->assertSame(3, $summary['games']);
        $this->assertSame(600 + 360, $summary['minutes']);
        $this->assertSame(1, $summary['unknown']);
    }

    public function test_page_renders_and_start_playing_changes_status(): void
    {
        $me = User::factory()->admin()->create();
        $entry = $this->entry($me, 'Hades', 'backlog', 1200);

        Livewire::actingAs($me)->test(PlayNext::class)
            ->assertSee('Hades')
            ->assertSee('Start playing')
            ->call('start', $entry->id)
            ->assertDontSee('Start playing');

        $this->assertSame('playing', $entry->fresh()->status->value);
        $this->actingAs($me)->get('/games/play-next')->assertOk();
    }

    public function test_igdb_time_to_beat_is_converted_to_minutes(): void
    {
        config(['gamehub.igdb.client_id' => 'id', 'gamehub.igdb.client_secret' => 'secret']);

        Http::fake([
            'id.twitch.tv/*' => Http::response(['access_token' => 't', 'expires_in' => 3600]),
            'api.igdb.com/v4/game_time_to_beats' => Http::sequence()
                ->push([['game_id' => 1, 'hastily' => 36000, 'normally' => 54000, 'completely' => 108000, 'count' => 40]])
                ->push([['game_id' => 2, 'hastily' => 7200]])
                ->push([]),
        ]);

        $igdb = app(IgdbClient::class);

        $this->assertSame(['main' => 900, 'complete' => 1800], $igdb->timeToBeat(1));
        $this->assertSame(['main' => 120, 'complete' => null], $igdb->timeToBeat(2));
        $this->assertNull($igdb->timeToBeat(3));
    }
}
