<?php

namespace App\Services\Providers\Psn;

/**
 * Pure translation of PlayStation's "played games" response into plain arrays (no framework needed,
 * so it is easy to test). Shapes below come from a real response for a PS5/PS4 account:
 *
 *   titleId            "PPSA02572_00" (PS5) / "CUSA08519_00" (PS4), one per game version
 *   category           "ps5_native_game" | "ps4_game" (others may exist, e.g. PC or unknown)
 *   concept.id         stable id for the game across its PS4 and PS5 versions
 *   playDuration       ISO-8601, e.g. "PT3H40M52S" or "PT63H58M50S"
 *   lastPlayedDateTime "2026-10-27T19:45:09.840000Z"
 */
final class PsnTitleMapper
{
    /**
     * @param  array<int, array<string, mixed>>  $titles
     * @return list<array{externalId:string,title:string,platformSlug:string,playtimeMinutes:int,lastPlayedAt:?string,coverUrl:?string}>
     */
    public static function map(array $titles): array
    {
        $games = [];

        foreach ($titles as $title) {
            $platform = self::platformSlug($title['category'] ?? null);

            if ($platform === null) {
                continue; // PC, PS3-era or unknown categories have no platform in the app
            }

            // concept.id is shared by the PS4 and PS5 version of one game, so it maps to one canonical Game.
            $externalId = (string) ($title['concept']['id'] ?? $title['titleId'] ?? '');

            if ($externalId === '') {
                continue;
            }

            $key = $externalId.'|'.$platform;
            $minutes = self::durationToMinutes($title['playDuration'] ?? null);
            $lastPlayed = $title['lastPlayedDateTime'] ?? null;

            if (! isset($games[$key])) {
                $games[$key] = [
                    'externalId' => $externalId,
                    'title' => (string) ($title['name'] ?? $title['localizedName'] ?? $externalId),
                    'platformSlug' => $platform,
                    'playtimeMinutes' => $minutes,
                    'lastPlayedAt' => $lastPlayed,
                    'coverUrl' => self::coverUrl($title),
                ];

                continue;
            }

            // Two editions of the same game on the same platform: one library entry, playtime added up.
            $games[$key]['playtimeMinutes'] += $minutes;

            if ($lastPlayed && (! $games[$key]['lastPlayedAt'] || strtotime($lastPlayed) > strtotime($games[$key]['lastPlayedAt']))) {
                $games[$key]['lastPlayedAt'] = $lastPlayed;
            }
        }

        return array_values($games);
    }

    public static function platformSlug(?string $category): ?string
    {
        $category = strtolower((string) $category);

        return match (true) {
            str_starts_with($category, 'ps5') => 'ps5',
            str_starts_with($category, 'ps4') => 'ps4',
            default => null,
        };
    }

    /** "PT3H40M52S" -> 221 (rounded to the nearest minute). Hours may exceed 24. */
    public static function durationToMinutes(?string $iso): int
    {
        if (! $iso || ! preg_match('/^P(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+(?:\.\d+)?)S)?)?$/', $iso, $m)) {
            return 0;
        }

        $seconds = (int) ($m[1] ?? 0) * 86400
            + (int) ($m[2] ?? 0) * 3600
            + (int) ($m[3] ?? 0) * 60
            + (float) ($m[4] ?? 0);

        return (int) round($seconds / 60);
    }

    /** Prefer the square-ish game hub cover art, then the master image, then the title icon. */
    public static function coverUrl(array $title): ?string
    {
        $images = array_merge($title['concept']['media']['images'] ?? [], $title['media']['images'] ?? []);

        foreach (['GAMEHUB_COVER_ART', 'MASTER'] as $type) {
            foreach ($images as $image) {
                if (($image['type'] ?? null) === $type && ! empty($image['url'])) {
                    return $image['url'];
                }
            }
        }

        return $title['imageUrl'] ?? null;
    }
}
