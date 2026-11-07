<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RunLogFeedTest extends TestCase
{
    use RefreshDatabase;

    private function feed(): array
    {
        return [
            'name' => 'Niko', 'slug' => 'niko', 'year' => 2026,
            'stats' => ['runs' => 42, 'distance_km' => 380.5, 'avg_pace' => '5:30', 'longest_km' => 21.1],
            'weekly' => [],
            'runs' => [['date' => '2026-11-05', 'type' => 'easy', 'distance_km' => 8.25, 'duration' => '45:10', 'pace' => '5:28', 'avg_hr' => 148]],
            'profile_url' => 'https://runlog.app/u/niko',
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_home_shows_runlog_link_and_recent_runs_when_configured(): void
    {
        config(['services.runlog.slug' => 'niko', 'services.runlog.url' => 'https://runlog.app']);
        Http::fake(['runlog.app/*' => Http::response($this->feed())]);

        $this->get('/en')->assertOk()
            ->assertSee('https://runlog.app/u/niko?lang=en', false)
            ->assertSee('8.25 km')
            ->assertSee('380.5 km');

        $this->get('/tr')->assertOk()->assertSee('https://runlog.app/u/niko?lang=tr', false);
    }

    public function test_home_still_loads_when_runlog_is_down(): void
    {
        config(['services.runlog.slug' => 'niko']);
        Http::fake(['runlog.app/*' => Http::response('boom', 500)]);

        $this->get('/en')->assertOk()->assertDontSee('id="runs"', false);
    }

    public function test_nothing_is_shown_without_a_slug(): void
    {
        config(['services.runlog.slug' => null]);
        Http::fake();

        $this->get('/en')->assertOk()->assertDontSee('runlog.app/u/', false);
        Http::assertNothingSent();
    }
}
