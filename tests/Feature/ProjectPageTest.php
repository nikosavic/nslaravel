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

    public function test_the_runsphere_migration_restructures_plain_text_once(): void
    {
        $project = Project::create([
            'title' => 'RunSphere', 'slug' => 'runsphere-ticket-platform', 'status' => 'published',
            'body_en' => "Overview\nPlain old text.", 'body_tr' => "Genel\nDüz metin.",
        ]);

        $migration = require database_path('migrations/2026_11_07_110000_restructure_runsphere_case_study.php');
        $migration->up();

        $this->assertStringContainsString('## Key features', $project->fresh()->body_en);
        $this->assertStringContainsString('## Temel özellikler', $project->fresh()->body_tr);

        // An edit made afterwards is left alone.
        $project->update(['body_en' => "## My own\nedit"]);
        $migration->up();
        $this->assertSame("## My own\nedit", $project->fresh()->body_en);

        $this->get('/en/projects/runsphere-ticket-platform')->assertOk()->assertSee('<h2>My own</h2>', false);
    }

    public function test_tuneout_case_study_is_added_in_both_languages(): void
    {
        $this->get('/en/projects/tuneout')->assertOk()
            ->assertSee('<h2>The problem</h2>', false)
            ->assertSee('Work in progress');

        $this->get('/tr/projects/tuneout')->assertOk()
            ->assertSee('<h2>Sorun</h2>', false)
            ->assertSee('Geliştirme sürüyor');
    }

    public function test_incubator_hub_case_study_is_added_in_both_languages(): void
    {
        $this->get('/en/projects/incubator-hub')->assertOk()
            ->assertSee('<h2>The problem</h2>', false)
            ->assertSee('Modbus TCP');

        $this->get('/tr/projects/incubator-hub')->assertOk()
            ->assertSee('<h2>Sorun</h2>', false);
    }

    public function test_race_timing_case_study_is_added_in_both_languages(): void
    {
        $this->get('/en/projects/race-timing')->assertOk()
            ->assertSee('<h2>The problem</h2>', false)
            ->assertSee('Working prototype')
            ->assertSee('RFID readers pick up the chip');

        $this->get('/tr/projects/race-timing')->assertOk()
            ->assertSee('<h2>Sorun</h2>', false)
            ->assertSee('RFID okuyucular');
    }

    public function test_runlog_case_study_is_added_in_both_languages(): void
    {
        $this->get('/en/projects/runlog')->assertOk()
            ->assertSee('<h2>The problem</h2>', false)
            ->assertSee('https://runlog.app');

        $this->get('/tr/projects/runlog')->assertOk()
            ->assertSee('<h2>Sorun</h2>', false);
    }

    public function test_runlog_is_marked_archived_without_a_live_link(): void
    {
        $this->get('/en/projects/runlog')->assertOk()
            ->assertSee('Archived, not currently hosted')
            ->assertDontSee('https://runlog.app');

        $this->get('/tr/projects/runlog')->assertOk()
            ->assertSee('Arşivlendi');
    }
}
