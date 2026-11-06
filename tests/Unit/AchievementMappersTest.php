<?php

namespace Tests\Unit;

use App\Services\Providers\Psn\PsnTrophyMapper;
use App\Services\Providers\Steam\SteamAchievementMapper;
use PHPUnit\Framework\TestCase;

class AchievementMappersTest extends TestCase
{
    public function test_steam_counts_unlocked_and_total(): void
    {
        $json = ['playerstats' => ['success' => true, 'achievements' => [
            ['apiname' => 'A', 'achieved' => 1],
            ['apiname' => 'B', 'achieved' => 0],
            ['apiname' => 'C', 'achieved' => 1],
        ]]];

        $this->assertSame(['unlocked' => 2, 'total' => 3], SteamAchievementMapper::map($json));
    }

    public function test_steam_game_without_achievements_is_total_zero(): void
    {
        $this->assertSame(['unlocked' => 0, 'total' => 0], SteamAchievementMapper::map(['playerstats' => ['error' => 'Requested app has no stats', 'success' => false]]));
        $this->assertSame(['unlocked' => 0, 'total' => 0], SteamAchievementMapper::map([]));
    }

    public function test_steam_private_profile_is_recognised(): void
    {
        $this->assertTrue(SteamAchievementMapper::isPrivate(['playerstats' => ['error' => 'Profile is not public', 'success' => false]]));
        $this->assertFalse(SteamAchievementMapper::isPrivate(['playerstats' => ['error' => 'Requested app has no stats', 'success' => false]]));
    }

    public function test_psn_trophies_are_summed_per_tier(): void
    {
        $rows = PsnTrophyMapper::map([[
            'trophyTitleName' => 'Hades Trophies',
            'trophyTitlePlatform' => 'PS5',
            'definedTrophies' => ['bronze' => 20, 'silver' => 8, 'gold' => 2, 'platinum' => 1],
            'earnedTrophies' => ['bronze' => 20, 'silver' => 8, 'gold' => 2, 'platinum' => 1],
        ], [
            'trophyTitleName' => 'Returnal',
            'trophyTitlePlatform' => 'PS4,PSVITA',
            'definedTrophies' => ['bronze' => 30, 'silver' => 5, 'gold' => 2, 'platinum' => 0],
            'earnedTrophies' => ['bronze' => 10, 'silver' => 1, 'gold' => 0, 'platinum' => 0],
        ]]);

        $this->assertCount(2, $rows);
        $this->assertSame('Hades', $rows[0]->title);
        $this->assertSame('ps5', $rows[0]->platformSlug);
        $this->assertSame([31, 31], [$rows[0]->unlocked, $rows[0]->total]);
        $this->assertSame(1, $rows[0]->trophies['platinum']);
        $this->assertSame('ps4', $rows[1]->platformSlug);
        $this->assertSame([11, 37], [$rows[1]->unlocked, $rows[1]->total]);
    }

    public function test_psn_titles_without_trophies_or_a_name_are_skipped(): void
    {
        $rows = PsnTrophyMapper::map([
            ['trophyTitleName' => 'No trophies', 'definedTrophies' => ['bronze' => 0, 'silver' => 0, 'gold' => 0, 'platinum' => 0]],
            ['trophyTitleName' => '', 'definedTrophies' => ['bronze' => 5]],
        ]);

        $this->assertSame([], $rows);
    }

    public function test_psn_platform_only_recognises_ps5_and_ps4(): void
    {
        $this->assertSame('ps5', PsnTrophyMapper::platform('PS5'));
        $this->assertSame('ps4', PsnTrophyMapper::platform('PS4'));
        $this->assertNull(PsnTrophyMapper::platform('PS3'));
        $this->assertNull(PsnTrophyMapper::platform('PSVITA'));
    }
}
