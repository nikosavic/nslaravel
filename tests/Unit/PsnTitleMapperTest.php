<?php

namespace Tests\Unit;

use App\Services\Providers\Psn\PsnTitleMapper;
use PHPUnit\Framework\TestCase;

class PsnTitleMapperTest extends TestCase
{
    public function test_durations_are_converted_to_rounded_minutes(): void
    {
        $this->assertSame(221, PsnTitleMapper::durationToMinutes('PT3H40M52S'));
        $this->assertSame(3839, PsnTitleMapper::durationToMinutes('PT63H58M50S'));
        $this->assertSame(2, PsnTitleMapper::durationToMinutes('PT1M52S'));
        $this->assertSame(0, PsnTitleMapper::durationToMinutes('PT0S'));
        $this->assertSame(60, PsnTitleMapper::durationToMinutes('PT1H'));
        $this->assertSame(1440, PsnTitleMapper::durationToMinutes('P1D'));
        $this->assertSame(0, PsnTitleMapper::durationToMinutes(null));
        $this->assertSame(0, PsnTitleMapper::durationToMinutes('garbage'));
    }

    public function test_only_ps4_and_ps5_categories_are_mapped(): void
    {
        $this->assertSame('ps5', PsnTitleMapper::platformSlug('ps5_native_game'));
        $this->assertSame('ps4', PsnTitleMapper::platformSlug('ps4_game'));
        $this->assertNull(PsnTitleMapper::platformSlug('pspc_game'));
        $this->assertNull(PsnTitleMapper::platformSlug('unknown'));
        $this->assertNull(PsnTitleMapper::platformSlug(null));
    }

    public function test_editions_of_one_game_on_one_platform_are_combined(): void
    {
        $games = PsnTitleMapper::map([
            ['titleId' => 'PPSA1_00', 'name' => 'Some Game', 'category' => 'ps5_native_game', 'concept' => ['id' => 7],
                'playDuration' => 'PT1H', 'lastPlayedDateTime' => '2026-01-01T10:00:00Z'],
            ['titleId' => 'PPSA2_00', 'name' => 'Some Game Deluxe', 'category' => 'ps5_native_game', 'concept' => ['id' => 7],
                'playDuration' => 'PT30M', 'lastPlayedDateTime' => '2026-02-01T10:00:00Z'],
            ['titleId' => 'CUSA3_00', 'name' => 'Some Game', 'category' => 'ps4_game', 'concept' => ['id' => 7],
                'playDuration' => 'PT2H', 'lastPlayedDateTime' => '2025-01-01T10:00:00Z'],
        ]);

        $this->assertCount(2, $games); // one ps5 entry (summed), one ps4 entry
        $this->assertSame(90, $games[0]['playtimeMinutes']);
        $this->assertSame('2026-02-01T10:00:00Z', $games[0]['lastPlayedAt']);
        $this->assertSame('ps4', $games[1]['platformSlug']);
        $this->assertSame('7', $games[0]['externalId'], 'the same concept id for both platforms');
    }

    public function test_cover_prefers_game_hub_art_then_master_then_icon(): void
    {
        $this->assertSame('hub', PsnTitleMapper::coverUrl(['concept' => ['media' => ['images' => [
            ['type' => 'MASTER', 'url' => 'master'], ['type' => 'GAMEHUB_COVER_ART', 'url' => 'hub'],
        ]]]]));
        $this->assertSame('master', PsnTitleMapper::coverUrl(['media' => ['images' => [['type' => 'MASTER', 'url' => 'master']]]]));
        $this->assertSame('icon', PsnTitleMapper::coverUrl(['imageUrl' => 'icon']));
    }
}
