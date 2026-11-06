<?php

namespace App\Services\Providers;

use App\Enums\ProviderType;
use App\Models\LinkedAccount;
use App\Services\Providers\Contracts\LibraryProvider;
use App\Services\Providers\Contracts\ProvidesAchievements;
use App\Services\Providers\DTO\AchievementProgress;
use App\Services\Providers\DTO\ProviderGame;
use App\Services\Providers\DTO\ResolvedAccount;
use App\Services\Providers\Exceptions\ProviderException;
use App\Services\Providers\Xbox\XboxLiveClient;
use App\Services\Providers\Xbox\XboxTitleMapper;
use App\Services\Providers\Xbox\XboxUnauthorized;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Xbox adapter with two ways in, chosen per linked account by what its stored credentials look like:
 *
 *  - direct (preferred): sign in to Microsoft with a device code and talk to Xbox Live yourself, through
 *    XboxLiveClient. Needs a free Azure app id in XBOX_CLIENT_ID. Gives games, last played, achievements and,
 *    for games that report it, minutes played.
 *  - OpenXBL (https://xbl.io): a third-party gateway, paste its API key. Gives games, last played and
 *    achievements, but no hours.
 */
class XboxProvider implements LibraryProvider, ProvidesAchievements
{
    private const BAD_KEY = 'OpenXBL did not accept that API key. Copy it again from your profile at xbl.io.';

    public function type(): ProviderType
    {
        return ProviderType::Xbox;
    }

    public function resolveAccount(string $input): ResolvedAccount
    {
        $key = $this->cleanKey($input);
        $data = $this->json($this->request($key, '/v2/account'));

        $user = $data['profileUsers'][0] ?? null;
        $xuid = (string) ($user['id'] ?? '');

        if ($xuid === '') {
            throw new ProviderException('OpenXBL did not return an Xbox profile for that key. Make sure you signed in with the Microsoft account you play on.');
        }

        $gamertag = null;

        foreach ($user['settings'] ?? [] as $setting) {
            if (($setting['id'] ?? '') === 'Gamertag') {
                $gamertag = $setting['value'] ?? null;
            }
        }

        return new ResolvedAccount(
            externalId: $xuid,
            displayName: $gamertag ?: 'Xbox account',
            credentials: ['api_key' => $key],
        );
    }

    /** Finishes a device-code login: Microsoft tokens -> Xbox identity -> the account to store (credentials included). */
    public function completeLogin(array $tokens): ResolvedAccount
    {
        $identity = app(XboxLiveClient::class)->identity($tokens['access_token']);

        return new ResolvedAccount(
            externalId: $identity['xuid'],
            displayName: $identity['gamertag'] ?: 'Xbox account',
            credentials: [
                'mode' => 'direct',
                'refresh_token' => $tokens['refresh_token'],
                'xsts' => $identity,
            ],
        );
    }

    public function fetchLibrary(LinkedAccount $account): iterable
    {
        foreach ($this->titles($account) as $title) {
            yield new ProviderGame(
                externalId: $title['externalId'],
                title: $title['title'],
                platformSlug: $title['platformSlug'],
                playtimeMinutes: (int) ($title['playtimeMinutes'] ?? 0), // 0 through OpenXBL; best effort when direct
                lastPlayedAt: $title['lastPlayedAt'] ? CarbonImmutable::parse($title['lastPlayedAt']) : null,
                coverUrl: $title['coverUrl'],
            );
        }
    }

    public function fetchAchievements(LinkedAccount $account): iterable
    {
        foreach ($this->titles($account) as $title) {
            if ($title['total'] > 0) {
                yield new AchievementProgress(
                    unlocked: min($title['unlocked'], $title['total']),
                    total: $title['total'],
                    externalId: $title['externalId'],
                );
            }
        }
    }

    /** @return list<array<string, mixed>> */
    private function titles(LinkedAccount $account): array
    {
        $credentials = $account->credentials ?? [];

        if (($credentials['mode'] ?? '') === 'direct' || isset($credentials['refresh_token'])) {
            return $this->directTitles($account);
        }

        $key = (string) ($account->credentials['api_key'] ?? '');

        if ($key === '') {
            throw new ProviderException('The OpenXBL key is missing. Link Xbox again on the Accounts page.');
        }

        $data = $this->json($this->request($key, (string) config('gamehub.xbox.titles_path')));

        return XboxTitleMapper::map($data['titles'] ?? []);
    }

    /** @return list<array<string, mixed>> */
    private function directTitles(LinkedAccount $account): array
    {
        $client = app(XboxLiveClient::class);
        $identity = $client->session($account);

        try {
            $raw = $client->titleHistory($identity);
        } catch (XboxUnauthorized) {
            // The short-lived token was refused: renew it once and retry.
            $identity = $client->session($account, force: true);
            $raw = $client->titleHistory($identity);
        }

        $titles = XboxTitleMapper::map($raw);
        $minutes = $client->minutesPlayed($identity, array_column($titles, 'externalId'));

        foreach ($titles as &$title) {
            $title['playtimeMinutes'] = $minutes[$title['externalId']] ?? 0;
        }
        unset($title);

        return $titles;
    }

    private function cleanKey(string $input): string
    {
        $key = trim($input, " \t\n\r\"'");

        if (! preg_match('/^[A-Za-z0-9._-]{16,128}$/', $key)) {
            throw new ProviderException('That does not look like an OpenXBL API key. It is a long string shown on your profile page at xbl.io.');
        }

        return $key;
    }

    private function request(string $key, string $path): Response
    {
        try {
            return Http::baseUrl((string) config('gamehub.xbox.base_url'))
                ->withHeaders(['X-Authorization' => $key])
                ->acceptJson()
                ->timeout(40)
                ->retry(2, 800, fn ($e) => $e instanceof ConnectionException, throw: false)
                ->get($path);
        } catch (ConnectionException) {
            throw new ProviderException('Could not reach OpenXBL. Try again in a moment.');
        }
    }

    /** @return array<string, mixed> */
    private function json(Response $response): array
    {
        $status = $response->status();

        if (in_array($status, [401, 403], true)) {
            throw new ProviderException(self::BAD_KEY);
        }

        if ($status === 429) {
            throw new ProviderException('OpenXBL is rate limiting requests (the free plan allows 150 an hour). Try again later.');
        }

        if ($response->failed()) {
            throw new ProviderException("OpenXBL error (HTTP {$status}).");
        }

        return $response->json() ?? [];
    }
}
