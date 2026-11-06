<?php

namespace Tests\Feature;

use App\Enums\StatsPeriod;
use App\Http\Controllers\ExportController;
use App\Livewire\Stats;
use App\Models\Game;
use App\Models\LibraryEntry;
use App\Models\Platform;
use App\Models\PlaytimeSnapshot;
use App\Models\User;
use App\Services\PlaytimeSnapshotter;
use App\Services\StatsService;
use Carbon\CarbonImmutable;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StatsTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlatformSeeder::class);
        $this->now = CarbonImmutable::parse('2026-10-30 12:00:00');
    }

    private function entry(User $user, string $title, string $platform = 'steam', array $attrs = []): LibraryEntry
    {
        return LibraryEntry::create($attrs + [
            'user_id' => $user->id,
            'game_id' => Game::create(['title' => $title, 'genres' => ['RPG']])->id,
            'platform_id' => Platform::where('slug', $platform)->firstOrFail()->id,
            'status' => 'playing',
        ]);
    }

    private function snap(LibraryEntry $entry, int $daysAgo, int $minutes): void
    {
        PlaytimeSnapshot::create([
            'library_entry_id' => $entry->id,
            'taken_on' => $this->now->subDays($daysAgo)->toDateString(),
            'minutes' => $minutes,
        ]);
    }

    public function test_first_snapshot_is_a_starting_point_and_growth_is_time_played(): void
    {
        $me = User::factory()->admin()->create();
        $hades = $this->entry($me, 'Hades');

        $this->snap($hades, 20, 6000); // first seen with 100 h already: not counted
        $this->snap($hades, 10, 6120); // +2 h, inside the month, outside the week
        $this->snap($hades, 3, 6300);  // +3 h, inside the week

        $service = app(StatsService::class);

        $week = $service->report($me->id, StatsPeriod::Week, $this->now);
        $month = $service->report($me->id, StatsPeriod::Month, $this->now);

        $this->assertSame(180, $week['minutes']);
        $this->assertSame(300, $month['minutes']);
        $this->assertSame(1, $week['gamesPlayed']);
        $this->assertSame(1, $week['activeDays']);
        $this->assertCount(7, $week['series']);
        $this->assertCount(30, $month['series']);
        $this->assertSame(180, collect($week['series'])->sum('minutes'));
    }

    public function test_period_comparison_and_percentage_change(): void
    {
        $me = User::factory()->admin()->create();
        $game = $this->entry($me, 'Celeste');

        $this->snap($game, 20, 0);
        $this->snap($game, 10, 60);  // 1 h in the previous week window of the month report? no: month = last 30 days
        $this->snap($game, 3, 240);  // +3 h this week

        $week = app(StatsService::class)->report($me->id, StatsPeriod::Week, $this->now);

        // previous 7 days (days 13..7) contained the +1 h at day 10
        $this->assertSame(180, $week['minutes']);
        $this->assertSame(60, $week['previousMinutes']);
        $this->assertSame(200, StatsService::change($week['minutes'], $week['previousMinutes']));
        $this->assertNull(StatsService::change(10, 0));
        $this->assertNull(StatsService::change(10, null));
    }

    public function test_other_users_history_is_never_counted(): void
    {
        $me = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();
        $theirs = $this->entry($other, 'Secret');

        $this->snap($theirs, 5, 0);
        $this->snap($theirs, 2, 600);

        $report = app(StatsService::class)->report($me->id, StatsPeriod::Week, $this->now);

        $this->assertSame(0, $report['minutes']);
        $this->assertFalse($report['hasHistory']);
    }

    public function test_platform_and_genre_split_and_top_games(): void
    {
        $me = User::factory()->admin()->create();
        $pc = $this->entry($me, 'Hades', 'steam');
        $ps = $this->entry($me, 'Returnal', 'ps5');

        $this->snap($pc, 5, 0);
        $this->snap($pc, 2, 120);
        $this->snap($ps, 5, 0);
        $this->snap($ps, 1, 60);

        $report = app(StatsService::class)->report($me->id, StatsPeriod::Week, $this->now);

        $this->assertSame('Hades', $report['top'][0]['entry']->game->title);
        $this->assertSame(['Steam', 'PlayStation 5'], array_column($report['platforms'], 'name'));
        $this->assertSame(180, $report['genres'][0]['minutes']);
    }

    public function test_year_report_has_twelve_monthly_bars(): void
    {
        $me = User::factory()->admin()->create();
        $report = app(StatsService::class)->report($me->id, StatsPeriod::Year, $this->now);

        $this->assertCount(12, $report['series']);
        $this->assertSame('month', $report['grain']);
    }

    public function test_snapshotter_only_writes_when_the_total_changes(): void
    {
        $me = User::factory()->admin()->create();
        $entry = $this->entry($me, 'Hades', 'steam', ['playtime_minutes' => 0]);
        $snapshotter = app(PlaytimeSnapshotter::class);

        $snapshotter->record($entry);
        $snapshotter->record($entry);
        $this->assertSame(1, PlaytimeSnapshot::count());

        $entry->playtime_minutes = 90;
        $snapshotter->record($entry); // same day: updates today's row
        $this->assertSame(1, PlaytimeSnapshot::count());
        $this->assertSame(90, PlaytimeSnapshot::first()->minutes);
    }

    public function test_stats_page_renders_with_and_without_history(): void
    {
        $me = User::factory()->admin()->create();

        Livewire::actingAs($me)->test(Stats::class)
            ->assertSee('Stats build up from your syncs')
            ->call('setPeriod', 'week')
            ->assertSet('period', 'week')
            ->call('setPeriod', 'nonsense')
            ->assertSet('period', 'month');

        $this->actingAs($me)->get('/games/stats')->assertOk()->assertSee('Hours played');
    }

    public function test_csv_export_neutralises_spreadsheet_formulas(): void
    {
        $this->assertSame("'=HYPERLINK(\"x\")", ExportController::safe('=HYPERLINK("x")'));
        $this->assertSame('Hades', ExportController::safe('Hades'));
        $this->assertSame(12.5, ExportController::safe(12.5));
    }

    public function test_csv_exports_download_for_the_signed_in_user(): void
    {
        $me = User::factory()->admin()->create();
        $this->entry($me, '=cmd|calc', 'steam');
        $this->actingAs($me);

        $library = $this->get('/games/export/library.csv');
        $library->assertOk();
        $this->assertStringContainsString("'=cmd|calc", $library->streamedContent());

        $this->get('/games/export/stats.csv?period=week')->assertOk();
    }
}
