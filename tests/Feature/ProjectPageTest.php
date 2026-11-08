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
            ->assertSee('<h2>The problem</h2>', false);

        $this->get('/tr/projects/runlog')->assertOk()
            ->assertSee('<h2>Sorun</h2>', false);
    }

    public function test_runlog_is_live_again_with_a_link(): void
    {
        $this->get('/en/projects/runlog')->assertOk()
            ->assertSee('href="https://runlog.app"', false)
            ->assertDontSee('Archived, not currently hosted')
            ->assertSee('Gives each runner an optional public page')
            ->assertSee('start and the end trimmed');

        $this->get('/tr/projects/runlog')->assertOk()
            ->assertSee('Yayında')
            ->assertDontSee('Arşivlendi')
            ->assertSee('ısı haritası');
    }

    public function test_project_page_uses_the_site_logo_for_link_previews_without_screenshots(): void
    {
        $this->get('/en/projects/runlog')->assertOk()
            ->assertSee('<meta property="og:image" content="'.asset('images/ns-logo.png').'">', false)
            ->assertSee('<meta name="twitter:card" content="summary">', false)
            ->assertSee('<meta property="og:description" content="A training platform', false);
    }

    public function test_screenshots_in_the_project_folder_become_cover_gallery_and_share_image(): void
    {
        $dir = public_path('images/projects/runlog');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');

        mkdir($dir, 0777, true);

        try {
            foreach (['cover.png', 'og.png', 'shot-1.png', 'shot-2.png'] as $file) {
                file_put_contents($dir.'/'.$file, $png);
            }

            $html = $this->get('/en/projects/runlog')->assertOk()->getContent();

            $this->assertStringContainsString('<meta property="og:image" content="'.asset('images/projects/runlog/og.png').'">', $html);
            $this->assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $html);
            $this->assertStringContainsString('src="'.asset('images/projects/runlog/cover.png').'"', $html);
            $this->assertStringContainsString('images/projects/runlog/shot-1.png', $html);
            $this->assertStringContainsString('images/projects/runlog/shot-2.png', $html);
            // cover and share image are not repeated in the gallery
            $this->assertSame(0, substr_count($html, 'alt="RunLog, screenshot 3"'));

            $this->get('/en/projects')->assertOk()
                ->assertSee('images/projects/runlog/cover.png', false);
        } finally {
            array_map('unlink', glob($dir.'/*') ?: []);
            rmdir($dir);
        }
    }
}
