<?php

namespace App\Services\Providers;

use App\Enums\ProviderType;
use App\Models\LinkedAccount;
use App\Models\LibraryEntry;
use App\Services\Providers\Contracts\LibraryProvider;
use App\Services\Providers\Contracts\ProvidesAchievements;
use App\Services\Providers\DTO\AchievementProgress;
use App\Services\Providers\DTO\ProviderGame;
use App\Services\Providers\DTO\ResolvedAccount;
use App\Services\Providers\Exceptions\ProviderException;
use App\Services\Providers\Steam\SteamAchievementMapper;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * Steam Web API adapter. Needs STEAM_API_KEY and a profile whose "Game details" are public.
 * Endpoints used: ResolveVanityURL, GetPlayerSummaries, GetOwnedGames, GetPlayerAchievements.
 */
class SteamProvider implements LibraryProvider, ProvidesAchievements
{
    private const BASE_URL = 'https://api.steampowered.com';

    /** One achievements call per game, so each run handles a batch; the rest follow on the next sync. */
    private const ACHIEVEMENT_BATCH = 300;

    public function type(): ProviderType
    {
        return ProviderType::Steam;
    }

    public function resolveAccount(string $input): ResolvedAccount
    {
        $input = trim($input);
        $steamId = null;
        $vanity = null;

        if (preg_match('~steamcommunity\.com/profiles/(\d{17})~i', $input, $m)) {
            $steamId = $m[1];
        } elseif (preg_match('~steamcommunity\.com/id/([^/?#]+)~i', $input, $m)) {
            $vanity = $m[1];
        } elseif (preg_match('/^\d{17}$/', $input)) {
            $steamId = $input;
        } elseif ($input !== '') {
            $vanity = $input;
        } else {
            throw new ProviderException('Enter a SteamID64, a profile URL or your custom profile name.');
        }

        if ($vanity !== null) {
            $resolved = $this->get('/ISteamUser/ResolveVanityURL/v1/', ['vanityurl' => $vanity]);

            if ((int) ($resolved['response']['success'] ?? 0) !== 1) {
                throw new ProviderException("No Steam profile found for \"{$vanity}\".");
            }

            $steamId = (string) $resolved['response']['steamid'];
        }

        $summary = $this->get('/ISteamUser/GetPlayerSummaries/v2/', ['steamids' => $steamId]);
        $player = $summary['response']['players'][0] ?? null;

        if (! $player) {
            throw new ProviderException("No Steam profile found for ID {$steamId}.");
        }

        // communitystatevisibilitystate: 3 = public
        $warning = (int) ($player['communitystatevisibilitystate'] ?? 1) === 3
            ? null
            : 'This profile is not public. In Steam > Privacy settings, set "My profile" and "Game details" to Public or no games can be read.';

        return new ResolvedAccount($steamId, (string) ($player['personaname'] ?? $steamId), $warning);
    }

    public function fetchLibrary(LinkedAccount $account): iterable
    {
        $data = $this->get('/IPlayerService/GetOwnedGames/v1/', [
            'steamid' => $account->external_id,
            'include_appinfo' => 1,
            'include_played_free_games' => 1,
        ]);

        $games = $data['response']['games'] ?? null;

        if ($games === null) {
            // A genuinely empty library reports game_count 0; a private one returns an empty response.
            if ((int) ($data['response']['game_count'] ?? -1) === 0) {
                return;
            }

            throw new ProviderException(
                'Steam returned no library. Set "Game details" to Public in your Steam privacy settings, then sync again.'
            );
        }

        $cdn = rtrim((string) config('gamehub.steam.cdn'), '/');

        foreach ($games as $game) {
            $appId = (string) $game['appid'];

            yield new ProviderGame(
                externalId: $appId,
                title: (string) ($game['name'] ?? "Steam app {$appId}"),
                platformSlug: 'steam',
                playtimeMinutes: (int) ($game['playtime_forever'] ?? 0),
                lastPlayedAt: ! empty($game['rtime_last_played'])
                    ? CarbonImmutable::createFromTimestampUTC((int) $game['rtime_last_played'])
                    : null,
                coverUrl: "{$cdn}/steam/apps/{$appId}/header.jpg",
            );
        }
    }

    /**
     * Achievement progress for games you have played and have not checked since you last played them.
     * Games with no achievements are reported with a total of 0 so they are not asked about again.
     */
    public function fetchAchievements(LinkedAccount $account): iterable
    {
        $entries = LibraryEntry::query()
            ->with('game.externalIds')
            ->where('linked_account_id', $account->id)
            ->where('playtime_minutes', '>', 0)
            ->where(fn ($q) => $q->whereNull('achievements_synced_at')->orWhereColumn('last_played_at', '>', 'achievements_synced_at'))
            ->orderByRaw('achievements_synced_at IS NULL DESC')
            ->orderByDesc('last_played_at')
            ->limit(self::ACHIEVEMENT_BATCH)
            ->get();

        foreach ($entries as $entry) {
            $appId = $entry->game->externalIds->firstWhere('provider', 'steam')?->external_id;

            if (! $appId) {
                continue;
            }

            $json = $this->achievementsFor((string) $account->external_id, (string) $appId);

            if (SteamAchievementMapper::isPrivate($json)) {
                throw new ProviderException('Steam says your game details are private. Set "Game details" to Public to read achievements.');
            }

            $counts = SteamAchievementMapper::map($json);

            yield new AchievementProgress(unlocked: $counts['unlocked'], total: $counts['total'], externalId: (string) $appId);

            Sleep::for(120)->milliseconds();
        }
    }

    /** @return array<string, mixed> empty when the game has no stats (Steam answers 400 for those) */
    private function achievementsFor(string $steamId, string $appId): array
    {
        $key = (string) config('gamehub.steam.key');

        if ($key === '') {
            throw new ProviderException('STEAM_API_KEY is not set in .env.');
        }

        try {
            $response = Http::baseUrl(self::BASE_URL)
                ->acceptJson()
                ->timeout(30)
                ->get('/ISteamUserStats/GetPlayerAchievements/v1/', [
                    'key' => $key, 'steamid' => $steamId, 'appid' => $appId, 'format' => 'json',
                ]);
        } catch (ConnectionException $e) {
            throw new ProviderException('Could not reach Steam: '.str_replace($key, '***', $e->getMessage()), previous: $e);
        }

        if ($response->status() === 429) {
            throw new ProviderException('Steam is rate limiting requests. The rest will follow on the next sync.');
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new ProviderException('Steam refused the achievements request. Check STEAM_API_KEY, and that "Game details" is Public.');
        }

        if ($response->serverError()) {
            throw new ProviderException("Steam API error (HTTP {$response->status()}). Try again later.");
        }

        return $response->json() ?? [];
    }

    /** @return array<string, mixed> */
    private function get(string $path, array $query = []): array
    {
        $key = config('gamehub.steam.key');

        if (blank($key)) {
            throw new ProviderException('STEAM_API_KEY is not set in .env.');
        }

        try {
            $response = Http::baseUrl(self::BASE_URL)
                ->acceptJson()
                ->timeout(30)
                ->retry(2, 500, throw: false)
                ->get($path, $query + ['key' => $key, 'format' => 'json']);
        } catch (ConnectionException $e) {
            // The exception message can contain the request URL, which includes the API key.
            throw new ProviderException('Could not reach Steam: '.str_replace((string) $key, '***', $e->getMessage()), previous: $e);
        }

        if (in_array($response->status(), [401, 403], true)) {
            throw new ProviderException('Steam rejected the API key. Check STEAM_API_KEY.');
        }

        if ($response->failed()) {
            throw new ProviderException("Steam API error (HTTP {$response->status()}). Try again later.");
        }

        return $response->json() ?? [];
    }
}
