<?php

namespace Tests\Feature;

use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_gamehub_is_added_to_the_projects_list_by_the_migration(): void
    {
        $this->assertSame('published', Project::where('slug', 'gamehub')->value('status'));

        $this->get('/en/projects')->assertOk()->assertSee('GameHub');
        $this->get('/tr/projects')->assertOk()->assertSee('GameHub');
    }

    public function test_a_case_study_renders_as_structured_content(): void
    {
        $this->get('/en/projects/gamehub')->assertOk()
            ->assertSee('Laravel 12, Livewire 3', false)
            ->assertSee('<h2>The problem</h2>', false)
            ->assertSee('<li>Syncs Steam, PlayStation and Xbox libraries', false)
            ->assertSee('https://nikolasavic.dev/games');

        $this->get('/tr/projects/gamehub')->assertOk()
            ->assertSee('<h2>Sorun</h2>', false);
    }

    public function test_plain_text_bodies_still_render(): void
    {
        $project = Project::create([
            'title' => 'Plain', 'slug' => 'plain', 'status' => 'published',
            'body' => "Overview\nJust a few lines of text.\n\nFeatures:\n- One\n- Two",
        ]);

        $this->get('/en/projects/plain')->assertOk()
            ->assertSee('<h2>Overview</h2>', false)
            ->assertSee('<li>Two</li>', false);
    }
}
