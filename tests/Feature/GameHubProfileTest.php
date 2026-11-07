<?php

namespace Tests\Feature;

use App\Livewire\ProfileSettings;
use App\Models\Game;
use App\Models\LibraryEntry;
use App\Models\Platform;
use App\Models\User;
use App\Services\ProfileData;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GameHubProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlatformSeeder::class);
    }

    private function publicUser(string $slug = 'niko'): User
    {
        $user = User::factory()->admin()->create(['email' => 'secret.person@example.com']);
        $user->forceFill(['is_public' => true, 'public_slug' => $slug])->save();

        return $user;
    }

    private function entry(User $user, string $title, string $status, int $minutes = 0, array $extra = []): LibraryEntry
    {
        return LibraryEntry::create($extra + [
            'user_id' => $user->id,
            'game_id' => Game::create(['title' => $title])->id,
            'platform_id' => Platform::where('slug', 'steam')->firstOrFail()->id,
            'status' => $status,
            'playtime_minutes' => $minutes,
        ]);
    }

    public function test_a_public_profile_opens_without_signing_in_and_shows_only_safe_things(): void
    {
        $user = $this->publicUser();
        $this->entry($user, 'Hades', 'playing', 3000, ['notes' => 'PRIVATE NOTE', 'rating' => 9]);
        $this->entry($user, 'Celeste', 'finished', 600, ['finished_at' => now()]);

        $response = $this->get('/games/u/niko');

        $response->assertOk()
            ->assertSee('Hades')
            ->assertSee('Celeste')
            ->assertDontSee('secret.person@example.com')
            ->assertDontSee('PRIVATE NOTE')
            ->assertDontSee('noindex', false);
    }

    public function test_ratings_and_trophies_are_shown_on_the_public_page(): void
    {
        $user = $this->publicUser();
        $this->entry($user, 'Alan Wake 2', 'finished', 1800, [
            'rating' => 9, 'achievements_unlocked' => 40, 'achievements_total' => 50, 'trophies' => ['platinum' => 1],
        ]);
        $this->entry($user, 'Celeste', 'backlog', 0);

        $this->get('/games')->assertOk()
            ->assertSee('Alan Wake 2')
            ->assertSee('Rated 9 out of 10')
            ->assertSee('40')
            ->assertSee('Platinum');
    }

    public function test_the_public_page_filters_and_loads_more(): void
    {
        $user = $this->publicUser();
        $this->entry($user, 'Hades', 'playing', 3000);
        $this->entry($user, 'Celeste', 'finished', 600, ['finished_at' => now(), 'rating' => 10]);

        \Livewire\Livewire::test(\App\Livewire\PublicProfile::class, ['slug' => 'niko'])
            ->set('status', 'finished')
            ->assertSee('Celeste')
            ->set('search', 'zzzz')
            ->assertSee('No games match')
            ->call('clearFilters')
            ->assertSee('Hades')
            ->set('sort', 'rating')
            ->assertSee('Celeste');
    }

    public function test_private_and_unknown_profiles_are_a_plain_404(): void
    {
        $user = User::factory()->admin()->create();
        $user->forceFill(['is_public' => false, 'public_slug' => 'hidden'])->save();

        $this->get('/games/u/hidden')->assertNotFound();
        $this->get('/games/u/nobody')->assertNotFound();
    }

    public function test_other_users_games_never_appear_on_a_profile(): void
    {
        $user = $this->publicUser();
        $other = User::factory()->admin()->create();
        $this->entry($user, 'Mine', 'playing', 60);
        $this->entry($other, 'Not Mine', 'playing', 60);

        $this->get('/games/u/niko')->assertSee('Mine')->assertDontSee('Not Mine');
    }

    public function test_saving_settings_validates_and_stores_the_link(): void
    {
        $me = User::factory()->admin()->create();

        Livewire::actingAs($me)->test(ProfileSettings::class)
            ->set('isPublic', true)
            ->set('slug', '')
            ->call('save')
            ->assertHasErrors(['slug' => 'required'])
            ->set('slug', 'Bad Name!')
            ->call('save')
            ->assertHasErrors('slug')
            ->set('slug', 'Niko-Plays')
            ->call('save')
            ->assertHasNoErrors();

        $me->refresh();
        $this->assertTrue((bool) $me->is_public);
        $this->assertSame('niko-plays', $me->public_slug);
    }

    public function test_a_taken_link_is_rejected_but_your_own_is_fine(): void
    {
        $this->publicUser('niko');
        $me = User::factory()->admin()->create();

        Livewire::actingAs($me)->test(ProfileSettings::class)
            ->set('isPublic', true)
            ->set('slug', 'niko')
            ->call('save')
            ->assertHasErrors('slug');

        $owner = User::where('public_slug', 'niko')->first();

        Livewire::actingAs($owner)->test(ProfileSettings::class)
            ->set('slug', 'niko')
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_turning_it_off_makes_the_page_disappear(): void
    {
        $user = $this->publicUser();
        $this->get('/games/u/niko')->assertOk();

        Livewire::actingAs($user)->test(ProfileSettings::class)
            ->set('isPublic', false)
            ->call('save');

        $this->get('/games/u/niko')->assertNotFound();
    }

    public function test_profile_numbers_and_the_card_payload(): void
    {
        $user = $this->publicUser();
        $this->entry($user, 'Hades', 'finished', 6000, ['finished_at' => now()]);
        $this->entry($user, 'Celeste', 'backlog', 120);

        $profiles = app(ProfileData::class);
        $data = $profiles->for($user);

        $this->assertSame(2, $data['games']);
        $this->assertSame(102, $data['hours']);
        $this->assertSame(1, $data['finished']);
        $this->assertNull($data['avgRating']);
        $this->assertSame(0, $data['platinums']);

        $card = $profiles->card($data);
        $this->assertSame('niko', $card['handle']);
        $this->assertSame('Hades', $card['top'][0]['title']);
        $this->assertSame(100.0, $card['top'][0]['hours']);
        $this->assertArrayNotHasKey('email', $card);
    }

    public function test_settings_page_requires_sign_in_and_renders(): void
    {
        $this->get('/games/profile')->assertRedirect('/login');

        $this->actingAs(User::factory()->admin()->create())->get('/games/profile')->assertOk()->assertSee('Share card');
    }
}
