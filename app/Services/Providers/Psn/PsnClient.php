<?php

namespace App\Services\Providers\Psn;

use App\Services\Providers\Exceptions\ProviderAuthException;
use App\Services\Providers\Exceptions\ProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Minimal client for the unofficial PlayStation Network endpoints, mirroring what the community
 * `psn-api` library does (checked against psn-api 2.18.1):
 *
 *   NPSSO cookie  --GET authorize-->  access code  --POST token-->  access + refresh tokens
 *   refresh token --POST token-->     new access + refresh tokens
 *   GET m.np.playstation.com/api/gamelist/v2/users/me/titles       played games with playtime
 *
 * Sony can change any of this without notice. Everything PSN-specific lives in this file and the mapper.
 */
class PsnClient
{
    private const OAUTH = 'https://ca.account.sony.com/api/authz/v3/oauth';

    // The public OAuth client of Sony's own PlayStation mobile app, the same one psn-api uses.
    private const CLIENT_ID = '09515159-7237-4370-9b40-3806e67c0891';
    private const BASIC_AUTH = 'Basic MDk1MTUxNTktNzIzNy00MzcwLTliNDAtMzgwNmU2N2MwODkxOnVjUGprYTV0bnRCMktxc1A=';
    private const REDIRECT_URI = 'com.scee.psxandroid.scecompcall://redirect';
    private const SCOPE = 'psn:mobile.v2.core psn:clientapp';

    private const GAMELIST = 'https://m.np.playstation.com/api/gamelist/v2/users/me/titles';
    private const PROFILE = 'https://m.np.playstation.com/api/userProfile/v1/internal/users/me/profiles';

    private const PAGE_SIZE = 200;
    private const MAX_PAGES = 50;

    /**
     * @return array{access_token:string, refresh_token:?string, refresh_expires_at:?int}
     *
     * @throws ProviderAuthException when the NPSSO token is rejected
     */
    public function tokensFromNpsso(string $npsso): array
    {
        $query = http_build_query([
            'access_type' => 'offline',
            'client_id' => self::CLIENT_ID,
            'redirect_uri' => self::REDIRECT_URI,
            'response_type' => 'code',
            'scope' => self::SCOPE,
        ]);

        $response = $this->send(fn () => Http::withHeaders(['Cookie' => 'npsso='.$npsso])
            ->withoutRedirecting()
            ->timeout(30)
            ->get(self::OAUTH.'/authorize?'.$query));

        $location = (string) $response->header('Location');

        if (! preg_match('/[?&]code=([^&]+)/', $location, $m)) {
            throw new ProviderAuthException(
                'PlayStation rejected the NPSSO token. Get a fresh one at https://ca.account.sony.com/api/v1/ssocookie (signed in to PlayStation) and link again.'
            );
        }

        return $this->token([
            'code' => urldecode($m[1]),
            'redirect_uri' => self::REDIRECT_URI,
            'grant_type' => 'authorization_code',
            'token_format' => 'jwt',
        ]);
    }

    /**
     * @return array{access_token:string, refresh_token:?string, refresh_expires_at:?int}
     *
     * @throws ProviderAuthException when the refresh token is no longer valid
     */
    public function tokensFromRefresh(string $refreshToken): array
    {
        return $this->token([
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
            'token_format' => 'jwt',
            'scope' => self::SCOPE,
        ]);
    }

    /**
     * Best effort: the account id and online id of the signed-in user. The shape of this response was not
     * captured, so every field is optional and a failure here never blocks linking.
     *
     * @return array{account_id:?string, online_id:?string}
     */
    public function profile(string $accessToken): array
    {
        try {
            $json = $this->authorized($accessToken)->timeout(30)->get(self::PROFILE)->json();
        } catch (\Throwable) {
            return ['account_id' => null, 'online_id' => null];
        }

        $profile = is_array($json['profile'] ?? null) ? $json['profile'] : (is_array($json) ? $json : []);

        return [
            'account_id' => isset($profile['accountId']) ? (string) $profile['accountId'] : null,
            'online_id' => isset($profile['onlineId']) ? (string) $profile['onlineId'] : null,
        ];
    }

    /**
     * Every game the account has played, as raw PSN title arrays, following pagination.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws ProviderException
     */
    public function playedTitles(string $accessToken): array
    {
        $titles = [];
        $offset = 0;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $response = $this->send(fn () => $this->authorized($accessToken)
                ->timeout(30)
                ->get(self::GAMELIST, ['limit' => self::PAGE_SIZE, 'offset' => $offset]));

            if (in_array($response->status(), [401, 403], true)) {
                throw new ProviderAuthException('PlayStation rejected the access token. Link your account again.');
            }

            $json = $response->json();

            if (! is_array($json) || ! array_key_exists('titles', $json)) {
                $message = is_array($json) ? ($json['error']['message'] ?? $json['message'] ?? null) : null;

                throw new ProviderException('PlayStation returned an unexpected response'.($message ? ": {$message}" : '').' (HTTP '.$response->status().').');
            }

            foreach ($json['titles'] as $title) {
                $titles[] = $title;
            }

            $next = $json['nextOffset'] ?? null;

            if ($next === null || (int) $next <= $offset) {
                break;
            }

            $offset = (int) $next;
        }

        return $titles;
    }

    /** @return array{access_token:string, refresh_token:?string, refresh_expires_at:?int} */
    private function token(array $form): array
    {
        $response = $this->send(fn () => Http::asForm()
            ->withHeaders(['Authorization' => self::BASIC_AUTH])
            ->timeout(30)
            ->post(self::OAUTH.'/token', $form));

        $json = $response->json();

        if ($response->failed() || ! is_array($json) || empty($json['access_token'])) {
            $detail = is_array($json) ? ($json['error_description'] ?? $json['error'] ?? null) : null;

            throw new ProviderAuthException('PlayStation sign-in failed'.($detail ? ": {$detail}" : '').'.');
        }

        return [
            'access_token' => (string) $json['access_token'],
            'refresh_token' => isset($json['refresh_token']) ? (string) $json['refresh_token'] : null,
            'refresh_expires_at' => isset($json['refresh_token_expires_in'])
                ? time() + (int) $json['refresh_token_expires_in']
                : null,
        ];
    }

    private function authorized(string $accessToken): PendingRequest
    {
        return Http::withToken($accessToken)->acceptJson();
    }

    /** @param  callable(): Response  $request */
    private function send(callable $request): Response
    {
        try {
            return $request();
        } catch (ConnectionException $e) {
            throw new ProviderException('Could not reach PlayStation. Try again later.', previous: $e);
        }
    }
}
