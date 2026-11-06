<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Livewire\Library;
use App\Models\Game;
use App\Models\LibraryEntry;
use App\Models\Platform;
use App\Models\User;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LibraryPageTest extends TestCase
{
    use RefreshDatabase;

    private Platform $steam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlatformSeeder::class);
        config(['gamehub.igdb.client_id' => null, 'gamehub.igdb.client_secret' => null]);

        $this->steam = Platform::where('slug', 'steam')->firstOrFail();
    }

    private function entry(User $user, string $title, string $status): LibraryEntry
    {
        return LibraryEntry::create([
            'user_id' => $user->id,
            'game_id' => Game::create(['title' => $title])->id,
            'platform_id' => $this->steam->id,
            'status' => $status,
        ]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/games/library')->assertRedirect('/login');
        $this->get('/games/accounts')->assertRedirect('/login');
    }

    public function test_pages_render_for_a_signed_in_user(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/games/library')->assertOk()->assertSee('GameHub');
        $this->get('/games/accounts')->assertOk()->assertSee('Link Steam');
    }

    public function test_library_shows_only_my_games_and_filters_by_status(): void
    {
        $me = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();

        $this->entry($me, 'Hades', 'playing');
        $this->entry($me, 'Celeste', 'backlog');
        $this->entry($other, 'Secret Game', 'playing');

        Livewire::actingAs($me)->test(Library::class)
            ->assertSee('Hades')
            ->assertSee('Celeste')
            ->assertDontSee('Secret Game')
            ->set('status', 'playing')
            ->assertSee('Hades')
            ->assertDontSee('Celeste');
    }

    public function test_search_filters_by_title(): void
    {
        $me = User::factory()->admin()->create();
        $this->entry($me, 'Hades', 'playing');
        $this->entry($me, 'Celeste', 'backlog');

        Livewire::actingAs($me)->test(Library::class)
            ->set('search', 'cele')
            ->assertSee('Celeste')
            ->assertDontSee('Hades');
    }

    public function test_changing_status_sets_dates_and_cannot_touch_other_users(): void
    {
        $me = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();
        $mine = $this->entry($me, 'Hades', 'playing');
        $theirs = $this->entry($other, 'Celeste', 'playing');

        Livewire::actingAs($me)->test(Library::class)
            ->call('setStatus', $mine->id, 'finished')
            ->call('setStatus', $theirs->id, 'finished');

        $this->assertSame(GameStatus::Finished, $mine->fresh()->status);
        $this->assertNotNull($mine->fresh()->finished_at);
        $this->assertSame(GameStatus::Playing, $theirs->fresh()->status);
    }

    public function test_rating_is_validated(): void
    {
        $me = User::factory()->admin()->create();
        $entry = $this->entry($me, 'Hades', 'playing');

        Livewire::actingAs($me)->test(Library::class)
            ->call('setRating', $entry->id, '9')
            ->call('setRating', $entry->id, '11');

        $this->assertSame(9, $entry->fresh()->rating);
    }

    public function test_now_playing_hero_shows_on_the_unfiltered_view_only(): void
    {
        $me = User::factory()->admin()->create();
        $this->entry($me, 'Hades', 'playing');
        $this->entry($me, 'Celeste', 'backlog');

        Livewire::actingAs($me)->test(Library::class)
            ->assertSee('Now playing')
            ->set('search', 'cele')
            ->assertDontSee('Now playing')
            ->call('clearFilters')
            ->assertSee('Now playing')
            ->set('status', 'backlog')
            ->assertDontSee('Now playing');
    }

    public function test_there_is_no_hero_without_a_playing_game(): void
    {
        $me = User::factory()->admin()->create();
        $this->entry($me, 'Celeste', 'backlog');

        Livewire::actingAs($me)->test(Library::class)
            ->assertDontSee('Now playing')
            ->assertSee('Celeste');
    }

    public function test_hero_shows_the_most_recently_played_game_and_counts_the_others(): void
    {
        $me = User::factory()->admin()->create();
        $old = $this->entry($me, 'Old Favourite', 'playing');
        $old->update(['last_played_at' => now()->subYear()]);
        $recent = $this->entry($me, 'Fresh Obsession', 'playing');
        $recent->update(['last_played_at' => now()->subDay()]);

        Livewire::actingAs($me)->test(Library::class)
            ->assertSeeHtml('<h2 class="hero-title">Fresh Obsession</h2>')
            ->assertSee('1 more in progress');
    }

    public function test_hero_button_finishes_the_game(): void
    {
        $me = User::factory()->admin()->create();
        $entry = $this->entry($me, 'Hades', 'playing');

        Livewire::actingAs($me)->test(Library::class)
            ->call('setStatus', $entry->id, 'finished')
            ->assertDontSee('Now playing');

        $this->assertSame(GameStatus::Finished, $entry->fresh()->status);
    }

    public function test_empty_states_tell_you_what_to_do(): void
    {
        $me = User::factory()->admin()->create();

        Livewire::actingAs($me)->test(Library::class)
            ->assertSee('Your library is empty')
            ->assertSee('Link a platform');

        $this->entry($me, 'Hades', 'playing');

        Livewire::actingAs($me)->test(Library::class)
            ->set('search', 'zzz')
            ->assertSee('No games match')
            ->assertSee('Clear filters');
    }

    public function test_add_game_manually(): void
    {
        $me = User::factory()->admin()->create();

        Livewire::actingAs($me)->test(Library::class)
            ->set('newTitle', 'Hollow Knight')
            ->set('newPlatformId', (string) $this->steam->id)
            ->set('newStatus', 'playing')
            ->call('addGame')
            ->assertHasNoErrors()
            ->assertSee('Hollow Knight');

        $entry = LibraryEntry::where('user_id', $me->id)->firstOrFail();
        $this->assertSame('manual', $entry->source);
        $this->assertSame(GameStatus::Playing, $entry->status);
        $this->assertNotNull($entry->started_at);
    }

    public function test_adding_the_same_game_twice_on_one_platform_is_rejected(): void
    {
        $me = User::factory()->admin()->create();
        $this->entry($me, 'Hades', 'backlog');

        Livewire::actingAs($me)->test(Library::class)
            ->set('newTitle', 'hades')
            ->set('newPlatformId', (string) $this->steam->id)
            ->call('addGame')
            ->assertHasErrors('newTitle');

        $this->assertSame(1, LibraryEntry::count());
    }
}
