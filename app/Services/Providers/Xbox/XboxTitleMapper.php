<?php

namespace App\Services\Providers\Xbox;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Maps OpenXBL title rows to GameHub fields. Pure, and deliberately tolerant about field names because
 * OpenXBL passes Microsoft's JSON through and Microsoft has changed it over the years.
 *
 * Row shape this expects (all fields optional except an id and a name):
 *   titleId, name, displayImage, devices: ["XboxSeries","XboxOne","Xbox360","PC"],
 *   titleHistory.lastTimePlayed, achievement.{currentAchievements,totalAchievements}
 */
class XboxTitleMapper
{
    /**
     * @param  array<int, array<string, mixed>>  $titles
     * @return list<array{externalId:string,title:string,platformSlug:string,lastPlayedAt:?string,coverUrl:?string,unlocked:int,total:int}>
     */
    public static function map(array $titles): array
    {
        $out = [];

        foreach ($titles as $row) {
            $id = (string) ($row['titleId'] ?? $row['id'] ?? '');
            $name = trim((string) ($row['name'] ?? ''));
            $platform = self::platform($row['devices'] ?? []);

            // Title history also lists apps (video, music, store). Only games belong in a game library.
            if (isset($row['type']) && strtolower((string) $row['type']) !== 'game') {
                continue;
            }

            if ($id === '' || $name === '' || $platform === null) {
                continue;
            }

            $out[] = [
                'externalId' => $id,
                'title' => $name,
                'platformSlug' => $platform,
                'lastPlayedAt' => self::date($row['titleHistory']['lastTimePlayed'] ?? $row['lastTimePlayed'] ?? null),
                'coverUrl' => self::cover($row['displayImage'] ?? null),
                'unlocked' => (int) ($row['achievement']['currentAchievements'] ?? 0),
                'total' => (int) ($row['achievement']['totalAchievements'] ?? 0),
            ];
        }

        return $out;
    }

    /** Series X|S wins when a title ran on both generations. PC-only and Xbox 360-only titles are skipped. */
    public static function platform(mixed $devices): ?string
    {
        $devices = array_map('strtolower', array_map('strval', is_array($devices) ? $devices : []));

        foreach ($devices as $device) {
            if (str_contains($device, 'series') || str_contains($device, 'scarlett')) {
                return 'xbox-series';
            }
        }

        foreach ($devices as $device) {
            if ($device === 'xboxone' || str_contains($device, 'xboxone') || $device === 'xbox one') {
                return 'xbox-one';
            }
        }

        return null;
    }

    private static function date(mixed $value): ?string
    {
        if (! is_string($value) || $value === '' || str_starts_with($value, '0001')) {
            return null; // Microsoft reports "never played" as year 0001
        }

        try {
            return CarbonImmutable::parse($value)->toIso8601String();
        } catch (Throwable) {
            return null;
        }
    }

    private static function cover(mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        return preg_replace('~^http://~i', 'https://', $url);
    }
}
