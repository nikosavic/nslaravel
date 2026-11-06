<?php

namespace App\Services\Providers\Psn;

/**
 * Pure mapping from PlayStation's "played games" response (gamelist/v2/users/{id}/titles, the "titles"
 * array) to plain arrays. No framework calls, so it can be tested against a real response file.
 *
 * What the real response looks like (verified against a captured sample):
 *   titleId            "PPSA02572_00"            one per platform edition
 *   name               "Alan Wake 2"
 *   category           "ps5_native_game" | "ps4_game" | ...
 *   playDuration       ISO 8601 duration, "PT3H40M52S" or "PT63H58M50S"
 *   lastPlayedDateTime "2026-10-27T19:45:09.840000Z"
 *   concept.id         stable id of the game across editions and PS4/PS5 versions
 *   concept.media.images[]  {type: GAMEHUB_COVER_ART | MASTER | ...}
 */
final class PsnLibraryMapper
{
    /** @var array<string, string> PSN category => platforms.slug */
    private const PLATFORMS = [
        'ps5_native_game' => 'ps5',
        'ps4_game' => 'ps4',
        'pspc_game' => 'pc-other',
    ];

    private const FALLBACK_PLATFORM = 'ps5';

    /**
     * One entry per (game, platform). If PSN lists several titles for the same game on the same platform
     * (different regions or editions), playtime is summed and the latest play date wins.
     *
     * @param  array<int, array<string, mixed>>  $titles
     * @return array<int, array{external_id:string,title:string,platform_slug:string,playtime_minutes:int,last_played_at:?string,cover_url:?string}>
     */
    public static function map(array $titles): array
    {
        $merged = [];

        foreach ($titles as $title) {
            $name = trim((string) ($title['name'] ?? $title['localizedName'] ?? ''));
            $titleId = (string) ($title['titleId'] ?? '');

            if ($name === '' || $titleId === '') {
                continue;
            }

            $conceptId = $title['concept']['id'] ?? null;
            $externalId = $conceptId !== null ? (string) $conceptId : 't-'.$titleId;
            $platform = self::PLATFORMS[(string) ($title['category'] ?? '')] ?? self::FALLBACK_PLATFORM;
            $key = $externalId.'|'.$platform;

            $row = [
                'external_id' => $externalId,
                'title' => $name,
                'platform_slug' => $platform,
                'playtime_minutes' => self::durationToMinutes((string) ($title['playDuration'] ?? '')),
                'last_played_at' => ($title['lastPlayedDateTime'] ?? null) ?: null,
                'cover_url' => self::cover($title),
            ];

            if (! isset($merged[$key])) {
                $merged[$key] = $row;

                continue;
            }

            $merged[$key]['playtime_minutes'] += $row['playtime_minutes'];
            $merged[$key]['cover_url'] ??= $row['cover_url'];

            if ($row['last_played_at'] !== null && $row['last_played_at'] > (string) $merged[$key]['last_played_at']) {
                $merged[$key]['last_played_at'] = $row['last_played_at'];
            }
        }

        return array_values($merged);
    }

    /** "PT63H58M50S" => 3838. Seconds are truncated; unparseable input counts as 0. */
    public static function durationToMinutes(string $iso): int
    {
        if (! preg_match('/^P(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+(?:\.\d+)?)S)?)?$/', $iso, $m)) {
            return 0;
        }

        $seconds = ((int) ($m[1] ?? 0)) * 86400
            + ((int) ($m[2] ?? 0)) * 3600
            + ((int) ($m[3] ?? 0)) * 60
            + (int) floor((float) ($m[4] ?? 0));

        return intdiv($seconds, 60);
    }

    private static function cover(array $title): ?string
    {
        $images = $title['concept']['media']['images'] ?? $title['media']['images'] ?? [];
        $byType = [];

        foreach ($images as $image) {
            if (isset($image['type'], $image['url'])) {
                $byType[$image['type']] ??= $image['url'];
            }
        }

        return $byType['GAMEHUB_COVER_ART'] ?? $byType['MASTER'] ?? ($title['imageUrl'] ?? null);
    }
}
