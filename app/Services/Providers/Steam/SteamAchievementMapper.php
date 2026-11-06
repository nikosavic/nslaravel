<?php

namespace App\Services\Providers\Steam;

/** Reads ISteamUserStats/GetPlayerAchievements. Pure, so it is easy to test. */
class SteamAchievementMapper
{
    /**
     * @param  array<string, mixed>  $json
     * @return array{unlocked:int,total:int}  total 0 means the game has no achievements (or no stats)
     */
    public static function map(array $json): array
    {
        $achievements = $json['playerstats']['achievements'] ?? [];

        if (! is_array($achievements) || $achievements === []) {
            return ['unlocked' => 0, 'total' => 0];
        }

        $unlocked = count(array_filter($achievements, fn ($a) => (int) ($a['achieved'] ?? 0) === 1));

        return ['unlocked' => $unlocked, 'total' => count($achievements)];
    }

    /** Steam answers 200 with success=false and "Profile is not public" when Game details are private. */
    public static function isPrivate(array $json): bool
    {
        return ($json['playerstats']['success'] ?? true) === false
            && str_contains(strtolower((string) ($json['playerstats']['error'] ?? '')), 'not public');
    }
}
