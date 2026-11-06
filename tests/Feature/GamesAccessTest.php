<?php

namespace Tests\Feature;

use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** GameHub lives inside the site under /games and is private to the admin. */
class GamesAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Route::has('games.entry')) {
            $this->markTestSkipped('Livewire is not installed, so GameHub is switched off.');
        }
    }

    public function test_a_normal_deploy_creates_the_platform_list(): void
    {
        $this->assertTrue(Platform::where('slug', 'steam')->exists());
        $this->assertTrue(Platform::where('slug', 'xbox-series')->exists());
    }

    public function test_guests_are_sent_to_the_normal_site_login(): void
    {
        $this->get('/games')->assertRedirect('/login');
        $this->get('/games/library')->assertRedirect('/login');
        $this->get('/games/stats')->assertRedirect('/login');
        $this->get('/games/accounts')->assertRedirect('/login');
    }

    public function test_the_menu_entry_takes_the_admin_to_the_library(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/games')->assertRedirect('/games/library');
    }

    public function test_a_signed_in_non_admin_cannot_open_the_private_pages(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/games/library')->assertForbidden();
        $this->get('/games/accounts')->assertForbidden();
        $this->get('/games/export/library.csv')->assertForbidden();
        $this->get('/games')->assertNotFound();
    }

    public function test_visitors_land_on_the_public_profile_when_the_owner_made_one(): void
    {
        $owner = User::factory()->admin()->create();
        $owner->forceFill(['is_public' => true, 'public_slug' => 'niko'])->save();

        $this->get('/games')->assertRedirect('/games/u/niko');
        $this->get('/games/u/niko')->assertOk()->assertSee('noindex', false);
    }

    public function test_a_private_profile_is_a_404(): void
    {
        $owner = User::factory()->admin()->create();
        $owner->forceFill(['is_public' => false, 'public_slug' => 'niko'])->save();

        $this->get('/games/u/niko')->assertNotFound();
    }

    public function test_the_admin_sees_gamehub_navigation_and_a_way_back_to_the_site(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/games/library')
            ->assertOk()
            ->assertSee('Play next')
            ->assertSee('nikolasavic.dev');
    }

    public function test_the_sync_jobs_are_on_the_scheduler(): void
    {
        Artisan::call('schedule:list');
        $output = Artisan::output();

        $this->assertStringContainsString('gamehub:sync', $output);
        $this->assertStringContainsString('gamehub:time-to-beat', $output);
        $this->assertStringContainsString('gamehub:monthly-summary', $output);
    }
}
