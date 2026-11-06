<?php

namespace Tests\Unit;

use App\Services\Providers\Xbox\XboxTitleMapper;
use PHPUnit\Framework\TestCase;

class XboxTitleMapperTest extends TestCase
{
    public function test_maps_a_series_title_with_achievements(): void
    {
        $rows = XboxTitleMapper::map([[
            'titleId' => '1234567',
            'name' => 'Forza Horizon 5',
            'displayImage' => 'http://store-images.s-microsoft.com/image/x.png',
            'devices' => ['XboxSeries', 'XboxOne', 'PC'],
            'titleHistory' => ['lastTimePlayed' => '2026-10-01T18:30:00Z'],
            'achievement' => ['currentAchievements' => 40, 'totalAchievements' => 120],
        ]]);

        $this->assertCount(1, $rows);
        $this->assertSame('xbox-series', $rows[0]['platformSlug']);
        $this->assertSame('1234567', $rows[0]['externalId']);
        $this->assertSame('https://store-images.s-microsoft.com/image/x.png', $rows[0]['coverUrl']);
        $this->assertSame([40, 120], [$rows[0]['unlocked'], $rows[0]['total']]);
        $this->assertStringStartsWith('2026-10-01T18:30:00', $rows[0]['lastPlayedAt']);
    }

    public function test_xbox_one_only_titles_get_the_one_platform(): void
    {
        $this->assertSame('xbox-one', XboxTitleMapper::platform(['XboxOne']));
        $this->assertSame('xbox-series', XboxTitleMapper::platform(['XboxOne', 'XboxSeries']));
    }

    public function test_pc_only_and_360_only_titles_are_skipped(): void
    {
        $this->assertNull(XboxTitleMapper::platform(['PC']));
        $this->assertNull(XboxTitleMapper::platform(['Xbox360']));
        $this->assertNull(XboxTitleMapper::platform('not an array'));

        $this->assertSame([], XboxTitleMapper::map([
            ['titleId' => '1', 'name' => 'Old game', 'devices' => ['Xbox360']],
            ['titleId' => '2', 'name' => 'PC only', 'devices' => ['PC']],
        ]));
    }

    public function test_never_played_date_and_missing_fields_are_tolerated(): void
    {
        $rows = XboxTitleMapper::map([
            ['titleId' => '9', 'name' => 'Halo', 'devices' => ['XboxOne'], 'titleHistory' => ['lastTimePlayed' => '0001-01-01T00:00:00Z']],
            ['name' => 'No id', 'devices' => ['XboxOne']],
            ['titleId' => '10', 'name' => '', 'devices' => ['XboxOne']],
        ]);

        $this->assertCount(1, $rows);
        $this->assertNull($rows[0]['lastPlayedAt']);
        $this->assertNull($rows[0]['coverUrl']);
        $this->assertSame(0, $rows[0]['total']);
    }

    public function test_apps_in_the_title_history_are_skipped(): void
    {
        $rows = XboxTitleMapper::map([
            ['titleId' => '1', 'name' => 'Netflix', 'type' => 'App', 'devices' => ['XboxSeries']],
            ['titleId' => '2', 'name' => 'Halo', 'type' => 'Game', 'devices' => ['XboxSeries']],
        ]);

        $this->assertSame(['Halo'], array_column($rows, 'title'));
    }
}
