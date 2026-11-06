<?php

namespace Tests\Feature;

use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    private function project(array $overrides = []): Project
    {
        return Project::create($overrides + [
            'title' => 'RunSphere',
            'title_en' => 'RunSphere',
            'title_tr' => 'RunSphere TR',
            'slug' => 'runsphere',
            'summary_en' => 'Ticket platform for runners',
            'summary_tr' => 'Koşucular için bilet platformu',
            'status' => 'published',
            'featured' => true,
        ]);
    }

    public function test_the_home_page_renders_in_both_languages_with_the_same_content(): void
    {
        $this->project();

        $this->get('/en')->assertOk()
            ->assertSee('Full-stack developer')
            ->assertSee('Building full-stack Laravel apps')
            ->assertSee('Ticket platform for runners')
            ->assertSee('Currently learning')
            ->assertSee('Built with Laravel');

        $this->get('/tr')->assertOk()
            ->assertSee('Full-stack geliştirici')
            ->assertSee('Koşucular için bilet platformu')
            ->assertSee('Hakkımda');
    }

    public function test_home_works_without_any_project_and_hides_the_admin_hint_from_visitors(): void
    {
        $this->get('/en')->assertOk()->assertDontSee('No featured project yet');
    }

    public function test_the_menu_has_the_game_hub_entry_when_game_hub_is_installed(): void
    {
        if (! \Illuminate\Support\Facades\Route::has('games.entry')) {
            $this->markTestSkipped('Livewire is not installed, so GameHub is switched off.');
        }

        $this->get('/en')->assertSee('gh-pill', false)->assertSee(route('games.entry'), false);
    }

    public function test_project_pages(): void
    {
        $project = $this->project();
        $draft = $this->project(['slug' => 'secret', 'title' => 'Secret', 'status' => 'draft', 'featured' => false]);

        $this->get('/en/projects')->assertOk()->assertSee('RunSphere')->assertDontSee('Secret');
        $this->get('/en/projects/'.$project->slug)->assertOk()->assertSee('RunSphere');
        $this->get('/en/projects/'.$draft->slug)->assertNotFound();
        $this->get('/tr/projects/'.$project->slug)->assertOk()->assertSee('RunSphere TR');
    }

    public function test_the_contact_page_and_form(): void
    {
        $this->get('/en/contact')->assertOk()->assertSee('name="website"', false);
        $this->get('/tr/contact')->assertOk()->assertSee('Gönder');

        // Missing fields are rejected with a visible error list.
        $this->from('/en/contact')->post('/en/contact', [])->assertSessionHasErrors(['name', 'email', 'message']);

        // A filled honeypot pretends to succeed and sends nothing.
        $this->post('/en/contact', ['name' => 'Bot', 'email' => 'bot@example.com', 'message' => 'hi', 'website' => 'spam'])
            ->assertSessionHas('success');
    }

    public function test_the_language_switch_works_for_pages_outside_the_locale_prefix(): void
    {
        $this->get('/lang/tr')->assertRedirect();
        $this->assertSame('tr', session('locale'));
    }
}
