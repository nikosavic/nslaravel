<?php

namespace App\Services\Igdb;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Thin IGDB (Twitch) client: token handling plus two lookups that return a normalised array
 * shaped for the games table. IGDB allows roughly 4 requests per second; EnrichGame paces itself.
 */
class IgdbClient
{
    private const TOKEN_CACHE_KEY = 'igdb.access_token';

    public function isConfigured(): bool
    {
        return filled(config('gamehub.igdb.client_id')) && filled(config('gamehub.igdb.client_secret'));
    }

    /** Exact lookup through IGDB's external_games table (Steam store URL carries the appid). */
    public function findBySteamAppId(string $appId): ?array
    {
        $appId = preg_replace('/\D/', '', $appId);

        if ($appId === '') {
            return null;
        }

        $rows = $this->query('external_games', sprintf(
            'fields uid,url,game.name,game.first_release_date,game.summary,game.cover.image_id,game.genres.name;'
            .' where uid = "%s"; limit 20;',
            $appId
        ));

        // uid alone is not unique across stores, so only accept rows pointing at the Steam store page.
        foreach ($rows as $row) {
            $url = (string) ($row['url'] ?? '');

            if (isset($row['game']['id']) && str_contains($url, "store.steampowered.com/app/{$appId}")) {
                return $this->mapGame($row['game']);
            }
        }

        return null;
    }

    /** Title search. Only an exact (normalised) name match is accepted; anything fuzzier stays unmatched. */
    public function searchByTitle(string $title): ?array
    {
        $needle = self::normalize($title);

        if ($needle === '') {
            return null;
        }

        $rows = $this->query('games', sprintf(
            'search "%s"; fields name,first_release_date,summary,cover.image_id,genres.name; limit 10;',
            addcslashes($title, '"\\')
        ));

        $matches = array_values(array_filter(
            $rows,
            fn (array $row) => self::normalize((string) ($row['name'] ?? '')) === $needle
        ));

        if ($matches === []) {
            return null;
        }

        // Prefer a result that has cover art; IGDB search order is otherwise kept.
        usort($matches, fn ($a, $b) => (int) isset($b['cover']['image_id']) <=> (int) isset($a['cover']['image_id']));

        return $this->mapGame($matches[0]);
    }

    /**
     * IGDB's crowd-sourced "time to beat", converted from seconds to minutes. Null when IGDB has no entry.
     * "main" is the usual playthrough ("normally", else "hastily"); "complete" is the everything-done run.
     *
     * @return array{main:?int,complete:?int}|null
     */
    public function timeToBeat(int $igdbId): ?array
    {
        $rows = $this->query('game_time_to_beats', sprintf(
            'fields game_id,hastily,normally,completely,count; where game_id = %d; limit 1;',
            $igdbId
        ));

        $row = $rows[0] ?? null;

        if (! $row) {
            return null;
        }

        $main = $row['normally'] ?? $row['hastily'] ?? null;
        $complete = $row['completely'] ?? null;

        if ($main === null && $complete === null) {
            return null;
        }

        return [
            'main' => $main !== null ? (int) round($main / 60) : null,
            'complete' => $complete !== null ? (int) round($complete / 60) : null,
        ];
    }

    public static function normalize(string $title): string
    {
        $title = Str::lower(str_replace(['™', '®', '©'], '', $title));
        $title = str_replace('&', ' and ', $title);

        return trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $title) ?? '');
    }

    /** @return array{igdb_id:int,title:string,cover_url:?string,release_date:?string,summary:?string,genres:?array} */
    private function mapGame(array $game): array
    {
        return [
            'igdb_id' => (int) $game['id'],
            'title' => (string) $game['name'],
            'cover_url' => isset($game['cover']['image_id'])
                ? "https://images.igdb.com/igdb/image/upload/t_cover_big/{$game['cover']['image_id']}.jpg"
                : null,
            'release_date' => isset($game['first_release_date'])
                ? Carbon::createFromTimestampUTC((int) $game['first_release_date'])->toDateString()
                : null,
            'summary' => $game['summary'] ?? null,
            'genres' => isset($game['genres']) ? array_values(array_column($game['genres'], 'name')) : null,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function query(string $endpoint, string $body): array
    {
        $response = Http::withHeaders([
            'Client-ID' => (string) config('gamehub.igdb.client_id'),
            'Authorization' => 'Bearer '.$this->token(),
        ])
            ->withBody($body, 'text/plain')
            ->timeout(20)
            ->retry(2, 1000, throw: false)
            ->post("https://api.igdb.com/v4/{$endpoint}");

        if ($response->status() === 401) {
            Cache::forget(self::TOKEN_CACHE_KEY); // token expired or revoked; the job retry fetches a new one
            throw new RuntimeException('IGDB rejected the access token.');
        }

        if ($response->failed()) {
            throw new RuntimeException("IGDB request to {$endpoint} failed (HTTP {$response->status()}).");
        }

        return $response->json() ?? [];
    }

    private function token(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);

        if ($cached) {
            return $cached;
        }

        $response = Http::asForm()->timeout(20)->post('https://id.twitch.tv/oauth2/token', [
            'client_id' => config('gamehub.igdb.client_id'),
            'client_secret' => config('gamehub.igdb.client_secret'),
            'grant_type' => 'client_credentials',
        ]);

        if ($response->failed() || ! $response->json('access_token')) {
            throw new RuntimeException('Could not get an IGDB access token. Check IGDB_CLIENT_ID and IGDB_CLIENT_SECRET.');
        }

        $token = (string) $response->json('access_token');
        $ttl = max(300, (int) $response->json('expires_in', 3600) - 600);
        Cache::put(self::TOKEN_CACHE_KEY, $token, $ttl);

        return $token;
    }
}
