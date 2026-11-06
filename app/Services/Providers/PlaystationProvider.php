<?php

namespace App\Services\Providers;

use App\Enums\ProviderType;
use App\Models\LinkedAccount;
use App\Services\Providers\Contracts\LibraryProvider;
use App\Services\Providers\Contracts\ProvidesAchievements;
use App\Services\Providers\DTO\ProviderGame;
use App\Services\Providers\DTO\ResolvedAccount;
use App\Services\Providers\Exceptions\ProviderException;
use App\Services\Providers\Psn\PsnTitleMapper;
use App\Services\Providers\Psn\PsnTrophyMapper;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * PlayStation adapter. Sony has no public API, so this talks to the same endpoints the community library
 * "psn-api" uses. Expect it to break occasionally: failures are recorded as a failed sync, never thrown.
 *
 * Login flow:
 *  1. The user pastes an NPSSO token (copied from https://ca.account.sony.com/api/v1/ssocookie while signed in).
 *  2. NPSSO -> authorization code -> access + refresh tokens. The NPSSO is used once and NOT stored.
 *  3. Only the tokens are kept, encrypted, in LinkedAccount::credentials. The access token is renewed
 *     with the refresh token; when that fails the user pastes a fresh NPSSO (same form, same account).
 *
 * Library: GET gamelist/v2/users/me/titles (played games with playtime), paged by nextOffset.
 */
class PlaystationProvider implements LibraryProvider, ProvidesAchievements
{
    private const AUTH_URL = 'https://ca.account.sony.com/api/authz/v3/oauth';
    private const REDIRECT_URI = 'com.scee.psxandroid.scecompcall://redirect';
    private const SCOPE = 'psn:mobile.v2.core psn:clientapp';
    private const GAMELIST_URL = 'https://m.np.playstation.com/api/gamelist/v2/users/me/titles';
    private const TROPHY_SUMMARY_URL = 'https://m.np.playstation.com/api/trophy/v1/users/me/trophySummary';
    private const PROFILE_URL = 'https://m.np.playstation.com/api/userProfile/v1/internal/users/me/profiles';
    private const TROPHY_TITLES_URL = 'https://m.np.playstation.com/api/trophy/v1/users/me/trophyTitles';
    private const PAGE_SIZE = 200;
    private const MAX_PAGES = 50;

    private const LOGIN_EXPIRED = 'PlayStation login expired. Paste a fresh NPSSO token on the Accounts page.';
    private const BAD_NPSSO = 'PlayStation did not accept that NPSSO token. Sign in at playstation.com, open https://ca.account.sony.com/api/v1/ssocookie and paste the new value.';

    public function type(): ProviderType
    {
        return ProviderType::Playstation;
    }

    public function resolveAccount(string $input): ResolvedAccount
    {
        $tokens = $this->tokenRequest([
            'code' => $this->codeFromNpsso($this->extractNpsso($input)),
            'redirect_uri' => self::REDIRECT_URI,
            'grant_type' => 'authorization_code',
            'token_format' => 'jwt',
        ]);

        $accessToken = (string) $tokens['access_token'];

        // The trophy summary is the cheapest call that reports the stable account id.
        $accountId = (string) ($this->json($this->get($accessToken, self::TROPHY_SUMMARY_URL))['accountId'] ?? '');

        if ($accountId === '') {
            throw new ProviderException('PlayStation did not return an account id. Try again with a fresh NPSSO token.');
        }

        $name = null;

        try {
            $name = $this->json($this->get($accessToken, self::PROFILE_URL))['onlineId'] ?? null;
        } catch (ProviderException) {
            // The display name is cosmetic; never fail the link over it.
        }

        return new ResolvedAccount(
            externalId: $accountId,
            displayName: $name ?: 'PlayStation account',
            credentials: $this->credentialsFrom($tokens),
        );
    }

    public function fetchLibrary(LinkedAccount $account): iterable
    {
        $accessToken = $this->accessToken($account);
        $refreshedOnce = false;
        $titles = [];
        $offset = 0;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $query = ['limit' => self::PAGE_SIZE, 'offset' => $offset];
            $response = $this->get($accessToken, self::GAMELIST_URL, $query);

            // Access tokens are short-lived: on a 401 renew once and retry before giving up.
            if ($response->status() === 401 && ! $refreshedOnce) {
                $refreshedOnce = true;
                $accessToken = $this->accessToken($account, force: true);
                $response = $this->get($accessToken, self::GAMELIST_URL, $query);
            }

            $data = $this->json($response);
            array_push($titles, ...($data['titles'] ?? []));

            $next = $data['nextOffset'] ?? null;

            if ($next === null || (int) $next <= $offset) {
                break;
            }

            $offset = (int) $next;
        }

        foreach (PsnTitleMapper::map($titles) as $game) {
            yield new ProviderGame(
                externalId: $game['externalId'],
                title: $game['title'],
                platformSlug: $game['platformSlug'],
                playtimeMinutes: $game['playtimeMinutes'],
                lastPlayedAt: $game['lastPlayedAt'] ? CarbonImmutable::parse($game['lastPlayedAt']) : null,
                coverUrl: $game['coverUrl'],
            );
        }
    }

    /** Trophy progress per game: GET trophy/v1/users/me/trophyTitles, paged by nextOffset. */
    public function fetchAchievements(LinkedAccount $account): iterable
    {
        $accessToken = $this->accessToken($account);
        $refreshedOnce = false;
        $titles = [];
        $offset = 0;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $query = ['limit' => 800, 'offset' => $offset];
            $response = $this->get($accessToken, self::TROPHY_TITLES_URL, $query);

            if ($response->status() === 401 && ! $refreshedOnce) {
                $refreshedOnce = true;
                $accessToken = $this->accessToken($account, force: true);
                $response = $this->get($accessToken, self::TROPHY_TITLES_URL, $query);
            }

            $data = $this->json($response);
            array_push($titles, ...($data['trophyTitles'] ?? []));

            $next = $data['nextOffset'] ?? null;

            if ($next === null || (int) $next <= $offset) {
                break;
            }

            $offset = (int) $next;
        }

        yield from PsnTrophyMapper::map($titles);
    }

    /** Accepts the bare token or the whole JSON page ({"npsso":"..."}) that Sony's cookie endpoint shows. */
    private function extractNpsso(string $input): string
    {
        $input = trim($input);

        if (preg_match('/"npsso"\s*:\s*"([^"]+)"/', $input, $m)) {
            $input = $m[1];
        }

        $input = trim($input, " \t\n\r\"'");

        if (! preg_match('/^[A-Za-z0-9_-]{20,200}$/', $input)) {
            throw new ProviderException('That does not look like an NPSSO token. It is a long string of letters and digits shown after "npsso" at ca.account.sony.com/api/v1/ssocookie.');
        }

        return $input;
    }

    private function codeFromNpsso(string $npsso): string
    {
        try {
            $response = Http::withHeaders(['Cookie' => 'npsso='.$npsso])
                ->withoutRedirecting()
                ->timeout(30)
                ->get(self::AUTH_URL.'/authorize', [
                    'access_type' => 'offline',
                    'client_id' => config('gamehub.playstation.client_id'),
                    'redirect_uri' => self::REDIRECT_URI,
                    'response_type' => 'code',
                    'scope' => self::SCOPE,
                ]);
        } catch (ConnectionException) {
            throw new ProviderException('Could not reach PlayStation. Try again in a moment.');
        }

        // A valid NPSSO answers with a redirect whose Location carries ?code=...
        if (! preg_match('/[?&]code=([^&#]+)/', (string) $response->header('Location'), $m)) {
            throw new ProviderException(self::BAD_NPSSO);
        }

        return urldecode($m[1]);
    }

    /**
     * @param  array<string, string>  $body
     * @return array<string, mixed>
     */
    private function tokenRequest(array $body): array
    {
        try {
            // No retries: authorization codes are single-use.
            $response = Http::asForm()
                ->withHeaders(['Authorization' => 'Basic '.config('gamehub.playstation.client_auth')])
                ->acceptJson()
                ->timeout(30)
                ->post(self::AUTH_URL.'/token', $body);
        } catch (ConnectionException) {
            throw new ProviderException('Could not reach PlayStation. Try again in a moment.');
        }

        $data = $response->json() ?? [];

        if ($response->failed() || empty($data['access_token'])) {
            throw new ProviderException(self::LOGIN_EXPIRED);
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function credentialsFrom(array $tokens, ?array $previous = null): array
    {
        return [
            'access_token' => $tokens['access_token'],
            'access_expires_at' => now()->addSeconds((int) ($tokens['expires_in'] ?? 3600))->toIso8601String(),
            // PlayStation may rotate the refresh token; keep the old one if it sends none.
            'refresh_token' => $tokens['refresh_token'] ?? ($previous['refresh_token'] ?? null),
            'refresh_expires_at' => isset($tokens['refresh_token_expires_in'])
                ? now()->addSeconds((int) $tokens['refresh_token_expires_in'])->toIso8601String()
                : ($previous['refresh_expires_at'] ?? null),
        ];
    }

    private function accessToken(LinkedAccount $account, bool $force = false): string
    {
        $credentials = $account->credentials ?? [];

        $stillValid = ! empty($credentials['access_token'])
            && ! empty($credentials['access_expires_at'])
            && CarbonImmutable::parse($credentials['access_expires_at'])->gt(now()->addSeconds(60));

        if ($stillValid && ! $force) {
            return $credentials['access_token'];
        }

        $refreshToken = $credentials['refresh_token'] ?? null;

        if (! $refreshToken
            || (! empty($credentials['refresh_expires_at']) && CarbonImmutable::parse($credentials['refresh_expires_at'])->isPast())) {
            throw new ProviderException(self::LOGIN_EXPIRED);
        }

        $tokens = $this->tokenRequest([
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
            'token_format' => 'jwt',
            'scope' => self::SCOPE,
        ]);

        $renewed = $this->credentialsFrom($tokens, $credentials);
        $account->update(['credentials' => $renewed]);

        return $renewed['access_token'];
    }

    private function get(string $accessToken, string $url, array $query = []): Response
    {
        try {
            return Http::withToken($accessToken)
                ->acceptJson()
                ->timeout(30)
                ->retry(
                    2,
                    500,
                    fn ($e) => $e instanceof ConnectionException
                        || ($e instanceof RequestException && $e->response->serverError()),
                    throw: false,
                )
                ->get($url, $query);
        } catch (ConnectionException) {
            throw new ProviderException('Could not reach PlayStation. Try again in a moment.');
        }
    }

    /** @return array<string, mixed> */
    private function json(Response $response): array
    {
        $status = $response->status();

        if ($status === 401) {
            throw new ProviderException(self::LOGIN_EXPIRED);
        }

        if ($status === 429) {
            throw new ProviderException('PlayStation is rate limiting requests. Try again later.');
        }

        if ($response->failed()) {
            throw new ProviderException("PlayStation API error (HTTP {$status}).");
        }

        $data = $response->json() ?? [];

        // Some PlayStation endpoints answer 200 with an error body.
        if (isset($data['error'])) {
            $message = is_array($data['error']) ? ($data['error']['message'] ?? 'unexpected error') : (string) $data['error'];

            throw new ProviderException("PlayStation API error: {$message}");
        }

        return $data;
    }
}
