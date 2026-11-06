<?php

namespace Tests\Feature;

use App\Livewire\Duplicates;
use App\Models\Game;
use App\Models\LibraryEntry;
use App\Models\Platform;
use App\Models\User;
use App\Services\DuplicateMerger;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DuplicateMergerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlatformSeeder::class);
    }

    private function entry(User $user, Game $game, string $platform, int $minutes = 0): LibraryEntry
    {
        return LibraryEntry::create([
            'user_id' => $user->id,
            'game_id' => $game->id,
            'platform_id' => Platform::where('slug', $platform)->firstOrFail()->id,
            'status' => 'backlog',
            'playtime_minutes' => $minutes,
        ]);
    }

    public function test_same_title_after_normalising_is_a_duplicate_but_a_remake_is_not(): void
    {
        $me = User::factory()->admin()->create();
        $this->entry($me, Game::create(['title' => 'ELDEN RING']), 'steam');
        $this->entry($me, Game::create(['title' => 'Elden Ring™']), 'ps5');
        $this->entry($me, Game::create(['title' => 'Resident Evil 4']), 'steam');
        $this->entry($me, Game::create(['title' => 'Resident Evil 4 Remake']), 'ps5');

        $groups = app(DuplicateMerger::class)->groups($me->id);

        $this->assertCount(1, $groups);
        $this->assertCount(2, $groups->first());
    }

    public function test_merging_moves_entries_and_fills_missing_details_on_the_kept_game(): void
    {
        $me = User::factory()->admin()->create();
        $keep = Game::create(['title' => 'Elden Ring']);
        $drop = Game::create(['title' => 'ELDEN RING', 'igdb_id' => 119133, 'cover_url' => 'https://img/x.jpg']);
        $drop->externalIds()->create(['provider' => 'playstation', 'external_id' => 'ps-1']);
        $this->entry($me, $keep, 'steam', 600);
        $psEntry = $this->entry($me, $drop, 'ps5', 300);

        $this->assertTrue(app(DuplicateMerger::class)->merge($me->id, $keep->id, $drop->id));

        $this->assertNull(Game::find($drop->id));
        $this->assertSame($keep->id, $psEntry->fresh()->game_id);
        $this->assertSame(119133, $keep->fresh()->igdb_id);
        $this->assertSame('https://img/x.jpg', $keep->fresh()->cover_url);
        $this->assertSame($keep->id, $keep->externalIds()->where('external_id', 'ps-1')->value('game_id'));
        $this->assertCount(0, app(DuplicateMerger::class)->groups($me->id));
    }

    public function test_games_igdb_says_are_different_are_never_merged(): void
    {
        $me = User::factory()->admin()->create();
        $a = Game::create(['title' => 'Doom', 'igdb_id' => 1]);
        $b = Game::create(['title' => 'DOOM', 'igdb_id' => 2]);
        $this->entry($me, $a, 'steam');
        $this->entry($me, $b, 'ps5');

        $this->assertFalse(app(DuplicateMerger::class)->merge($me->id, $a->id, $b->id));
        $this->assertNotNull(Game::find($b->id));
    }

    public function test_you_cannot_merge_games_that_are_not_a_listed_pair(): void
    {
        $me = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();
        $mine = Game::create(['title' => 'Hades']);
        $theirs = Game::create(['title' => 'Celeste']);
        $this->entry($me, $mine, 'steam');
        $this->entry($other, $theirs, 'steam');

        $this->assertFalse(app(DuplicateMerger::class)->merge($me->id, $mine->id, $theirs->id));
        $this->assertFalse(app(DuplicateMerger::class)->merge($me->id, $mine->id, $mine->id));
    }

    public function test_the_page_and_library_notice_work(): void
    {
        $me = User::factory()->admin()->create();
        $a = Game::create(['title' => 'Hades']);
        $b = Game::create(['title' => 'hades']);
        $this->entry($me, $a, 'steam');
        $this->entry($me, $b, 'ps5');

        $this->actingAs($me)->get('/games/library')->assertOk()->assertSee('Review and merge');

        Livewire::actingAs($me)->test(Duplicates::class)
            ->assertSee('Keep this one')
            ->call('merge', $a->id, $b->id)
            ->assertSee('Merged.')
            ->assertSee('No duplicates');
    }
}
