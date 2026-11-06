<?php

namespace App\Services\Providers\Psn;

use App\Services\Providers\DTO\AchievementProgress;

/** Turns PlayStation's trophyTitles response into AchievementProgress rows. Pure, so it is easy to test. */
class PsnTrophyMapper
{
    private const TIERS = ['bronze', 'silver', 'gold', 'platinum'];

    /**
     * @param  array<int, array<string, mixed>>  $trophyTitles
     * @return list<AchievementProgress>
     */
    public static function map(array $trophyTitles): array
    {
        $out = [];

        foreach ($trophyTitles as $title) {
            $name = trim((string) ($title['trophyTitleName'] ?? ''));
            $defined = $title['definedTrophies'] ?? [];
            $earned = $title['earnedTrophies'] ?? [];

            $total = array_sum(array_map(fn ($tier) => (int) ($defined[$tier] ?? 0), self::TIERS));

            if ($name === '' || $total === 0) {
                continue;
            }

            $earnedByTier = [];

            foreach (self::TIERS as $tier) {
                $earnedByTier[$tier] = (int) ($earned[$tier] ?? 0);
            }

            $out[] = new AchievementProgress(
                unlocked: min($total, array_sum($earnedByTier)),
                total: $total,
                title: self::cleanTitle($name),
                platformSlug: self::platform((string) ($title['trophyTitlePlatform'] ?? '')),
                trophies: $earnedByTier,
            );
        }

        return $out;
    }

    /** "Hades Trophies" and "Hades Trophy Set" are the same game as "Hades". */
    public static function cleanTitle(string $name): string
    {
        return trim(preg_replace('/\s+(trophy set|trophies|trophy)$/i', '', $name) ?? $name);
    }

    /** "PS5", "PS4", "PS4,PSVITA"... Only the two platforms GameHub tracks are returned. */
    public static function platform(string $platform): ?string
    {
        return match (true) {
            str_contains($platform, 'PS5') => 'ps5',
            str_contains($platform, 'PS4') => 'ps4',
            default => null,
        };
    }
}
