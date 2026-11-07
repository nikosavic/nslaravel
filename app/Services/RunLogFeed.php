<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Recent runs from the runner's public RunLog page (https://runlog.app/u/{slug}/runs.json).
 * Cached for 15 minutes; any failure returns null so the home page never breaks.
 */
class RunLogFeed
{
    public static function enabled(): bool
    {
        return (bool) config('services.runlog.slug');
    }

    public static function profileUrl(string $locale): ?string
    {
        if (! self::enabled()) {
            return null;
        }

        return rtrim(config('services.runlog.url'), '/') . '/u/' . config('services.runlog.slug') . '?lang=' . $locale;
    }

    /** @return array<string, mixed>|null */
    public static function get(): ?array
    {
        if (! self::enabled()) {
            return null;
        }

        $url = rtrim(config('services.runlog.url'), '/') . '/u/' . config('services.runlog.slug') . '/runs.json';

        try {
            return Cache::remember('runlog.feed', now()->addMinutes(15), function () use ($url) {
                $res = Http::timeout(3)->acceptJson()->get($url);

                if (! $res->successful() || ! is_array($res->json('runs'))) {
                    throw new \RuntimeException('RunLog feed unavailable');
                }

                return $res->json();
            });
        } catch (\Throwable $e) {
            return null;
        }
    }
}
