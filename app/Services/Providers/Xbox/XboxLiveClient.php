<?php

namespace App\Services\Providers\Xbox;

use App\Models\LinkedAccount;
use App\Services\Providers\Exceptions\ProviderException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Talks to Microsoft and Xbox Live directly, with no third-party service in between. This is the same sign-in
 * chain every Xbox launcher and companion app uses:
 *
 *   1. Device-code login: GameHub shows a short code, you enter it at microsoft.com/link and approve.
 *      Microsoft returns an access token and a long-lived refresh token (offline_access).
 *   2. That token is exchanged for an Xbox Live user token, then an XSTS token (about 16 hours).
 *   3. The XSTS token authorises calls to titlehub (games you have played, achievements) and
 *      userstats (minutes played, where a game reports it).
 *
 * You register a free "public client" app in the Azure portal once and put its id in XBOX_CLIENT_ID.
 * Only the refresh token and the short-lived XSTS token are stored, encrypted, on the linked account.
 *
 * This is written from the publicly known behaviour of these endpoints and has not been run against a real account.
 */
class XboxLiveClient
{
    private const DEVICE_URL = 'https://login.microsoftonline.com/consumers/oauth2/v2.0/devicecode';
    private const TOKEN_URL = 'https://login.microsoftonline.com/consumers/oauth2/v2.0/token';
    private const SCOPE = 'XboxLive.signin offline_access';
    private const USER_AUTH_URL = 'https://user.auth.xboxlive.com/user/authenticate';
    private const XSTS_URL = 'https://xsts.auth.xboxlive.com/xsts/authorize';
    private const TITLEHUB_URL = 'https://titlehub.xboxlive.com';
    private const USERSTATS_URL = 'https://userstats.xboxlive.com/batch';

    public function isConfigured(): bool
    {
        return filled(config('gamehub.xbox.client_id'));
    }

    /** @return array{device_code:string,user_code:string,verification_uri:string,expires_in:int,interval:int} */
    public function startDeviceLogin(): array
    {
        $this->requireClientId();

        $data = $this->form(self::DEVICE_URL, ['client_id' => $this->clientId(), 'scope' => self::SCOPE], 'start the Xbox login');

        if (empty($data['device_code']) || empty($data['user_code'])) {
            throw new ProviderException('Microsoft did not start a login. Check XBOX_CLIENT_ID and that "Allow public client flows" is on for the app.');
        }

        return [
            'device_code' => (string) $data['device_code'],
            'user_code' => (string) $data['user_code'],
            'verification_uri' => (string) ($data['verification_uri'] ?? 'https://www.microsoft.com/link'),
            'expires_in' => (int) ($data['expires_in'] ?? 900),
            'interval' => max(3, (int) ($data['interval'] ?? 5)),
        ];
    }

    /**
     * One poll of the device login. status: pending | slow_down | declined | expired | ok (then tokens is set).
     *
     * @return array{status:string,tokens?:array{access_token:string,refresh_token:?string,expires_in:int}}
     */
    public function pollDeviceLogin(string $deviceCode): array
    {
        $response = $this->post(self::TOKEN_URL, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:device_code',
            'client_id' => $this->clientId(),
            'device_code' => $deviceCode,
        ], 'check the Xbox login');

        $data = $response->json() ?? [];

        if ($response->successful() && ! empty($data['access_token'])) {
            return ['status' => 'ok', 'tokens' => $this->tokens($data)];
        }

        return match ($data['error'] ?? '') {
            'authorization_pending' => ['status' => 'pending'],
            'slow_down' => ['status' => 'slow_down'],
            'authorization_declined', 'access_denied' => ['status' => 'declined'],
            'expired_token', 'code_expired' => ['status' => 'expired'],
            default => throw new ProviderException('Microsoft stopped the login: '.($data['error_description'] ?? $data['error'] ?? 'unknown error').'.'),
        };
    }

    /**
     * Microsoft access token -> Xbox Live identity (XSTS token, user hash, xuid, gamertag).
     *
     * @return array{token:string,uhs:string,xuid:string,gamertag:?string,expires_at:string}
     */
    public function identity(string $msAccessToken): array
    {
        $user = $this->xbl(self::USER_AUTH_URL, [
            'Properties' => ['AuthMethod' => 'RPS', 'SiteName' => 'user.auth.xboxlive.com', 'RpsTicket' => 'd='.$msAccessToken],
            'RelyingParty' => 'http://auth.xboxlive.com',
            'TokenType' => 'JWT',
        ]);

        $xsts = $this->xbl(self::XSTS_URL, [
            'Properties' => ['SandboxId' => 'RETAIL', 'UserTokens' => [$user['Token'] ?? '']],
            'RelyingParty' => 'http://xboxlive.com',
            'TokenType' => 'JWT',
        ]);

        $claims = $xsts['DisplayClaims']['xui'][0] ?? [];

        if (empty($xsts['Token']) || empty($claims['uhs']) || empty($claims['xid'])) {
            throw new ProviderException('Xbox Live did not return a profile for this Microsoft account.');
        }

        return [
            'token' => (string) $xsts['Token'],
            'uhs' => (string) $claims['uhs'],
            'xuid' => (string) $claims['xid'],
            'gamertag' => isset($claims['gtg']) ? (string) $claims['gtg'] : null,
            'expires_at' => $this->expiry($xsts['NotAfter'] ?? null),
        ];
    }

    /**
     * A valid XSTS identity for the account, renewing it (and the Microsoft refresh token) when it is about to expire.
     *
     * @return array{token:string,uhs:string,xuid:string,gamertag:?string,expires_at:string}
     */
    public function session(LinkedAccount $account, bool $force = false): array
    {
        $credentials = $account->credentials ?? [];
        $xsts = $credentials['xsts'] ?? null;

        if (! $force && $xsts && ! empty($xsts['token']) && CarbonImmutable::parse($xsts['expires_at'])->gt(now()->addMinutes(5))) {
            return $xsts;
        }

        $refresh = (string) ($credentials['refresh_token'] ?? '');

        if ($refresh === '') {
            throw new ProviderException('Your Xbox login is missing. Link Xbox again on the Accounts page.');
        }

        $response = $this->post(self::TOKEN_URL, [
            'grant_type' => 'refresh_token',
            'client_id' => $this->clientId(),
            'refresh_token' => $refresh,
            'scope' => self::SCOPE,
        ], 'renew the Xbox login');

        $data = $response->json() ?? [];

        if ($response->failed() || empty($data['access_token'])) {
            throw new ProviderException('Your Xbox login expired. Link Xbox again on the Accounts page.');
        }

        $identity = $this->identity((string) $data['access_token']);

        $account->update(['credentials' => [
            'mode' => 'direct',
            'refresh_token' => $data['refresh_token'] ?? $refresh, // Microsoft rotates it: keep the newest
            'xsts' => $identity,
        ]]);

        return $identity;
    }

    /**
     * Every title the account has played, with achievements, images and last played. Same field names as OpenXBL,
     * so XboxTitleMapper reads both.
     *
     * @param  array{token:string,uhs:string,xuid:string}  $identity
     * @return array<int, array<string, mixed>>
     *
     * @throws XboxUnauthorized when the token was rejected (the caller renews it once and retries)
     */
    public function titleHistory(array $identity): array
    {
        $response = $this->xblGet(
            self::TITLEHUB_URL."/users/xuid({$identity['xuid']})/titles/titlehistory/decoration/achievement,image,scid",
            $identity,
            '2'
        );

        return $this->checked($response, 'read your Xbox games')['titles'] ?? [];
    }

    /**
     * Minutes played per title id, for games that report it (many do not). Best effort: any failure returns what
     * was read so far, because hours are a bonus and must never fail a sync.
     *
     * @param  array{token:string,uhs:string,xuid:string}  $identity
     * @param  list<string>  $titleIds
     * @return array<string,int>
     */
    public function minutesPlayed(array $identity, array $titleIds): array
    {
        $minutes = [];

        foreach (array_chunk($titleIds, 40) as $chunk) {
            try {
                $response = Http::withHeaders($this->headers($identity, '2'))
                    ->acceptJson()
                    ->timeout(40)
                    ->post(self::USERSTATS_URL, [
                        'arrangebyfield' => 'xuid',
                        'xuids' => [$identity['xuid']],
                        'stats' => array_map(fn ($id) => ['name' => 'MinutesPlayed', 'titleid' => (string) $id], $chunk),
                    ]);
            } catch (Throwable) {
                break;
            }

            if ($response->failed()) {
                break;
            }

            foreach (self::statRows($response->json() ?? []) as $row) {
                $id = (string) ($row['titleid'] ?? '');

                if ($id !== '' && ($row['name'] ?? '') === 'MinutesPlayed') {
                    $minutes[$id] = max(0, (int) ($row['value'] ?? 0));
                }
            }
        }

        return $minutes;
    }

    /** @return list<array<string, mixed>> flat list of stat rows from either response layout */
    public static function statRows(array $json): array
    {
        $rows = [];

        foreach ($json['statlistscollection'] ?? [] as $collection) {
            foreach ($collection['stats'] ?? [] as $stat) {
                $rows[] = $stat;
            }
        }

        return $rows;
    }

    // ---- plumbing ----

    private function clientId(): string
    {
        return (string) config('gamehub.xbox.client_id');
    }

    private function requireClientId(): void
    {
        if (! $this->isConfigured()) {
            throw new ProviderException('Set XBOX_CLIENT_ID in .env first (see the Xbox section of the README), then run sail artisan config:clear.');
        }
    }

    /** @return array{access_token:string,refresh_token:?string,expires_in:int} */
    private function tokens(array $data): array
    {
        return [
            'access_token' => (string) $data['access_token'],
            'refresh_token' => isset($data['refresh_token']) ? (string) $data['refresh_token'] : null,
            'expires_in' => (int) ($data['expires_in'] ?? 3600),
        ];
    }

    private function expiry(?string $notAfter): string
    {
        try {
            return $notAfter ? CarbonImmutable::parse($notAfter)->toIso8601String() : now()->addHours(12)->toIso8601String();
        } catch (Throwable) {
            return now()->addHours(12)->toIso8601String();
        }
    }

    /** @return array<string, mixed> */
    private function form(string $url, array $body, string $what): array
    {
        $response = $this->post($url, $body, $what);

        if ($response->failed()) {
            $data = $response->json() ?? [];

            throw new ProviderException('Microsoft refused to '.$what.': '.($data['error_description'] ?? $data['error'] ?? 'HTTP '.$response->status()).'.');
        }

        return $response->json() ?? [];
    }

    private function post(string $url, array $body, string $what): Response
    {
        try {
            // No retries: device codes and refresh tokens must not be replayed.
            return Http::asForm()->acceptJson()->timeout(30)->post($url, $body);
        } catch (ConnectionException) {
            throw new ProviderException("Could not reach Microsoft to {$what}. Try again in a moment.");
        }
    }

    /** @return array<string, mixed> */
    private function xbl(string $url, array $body): array
    {
        try {
            $response = Http::withHeaders(['x-xbl-contract-version' => '1'])->acceptJson()->asJson()->timeout(30)->post($url, $body);
        } catch (ConnectionException) {
            throw new ProviderException('Could not reach Xbox Live. Try again in a moment.');
        }

        if ($response->failed()) {
            throw new ProviderException(self::xerrMessage($response->json('XErr'), $response->status()));
        }

        return $response->json() ?? [];
    }

    public static function xerrMessage(mixed $code, int $status = 0): string
    {
        return match ((int) $code) {
            2148916233 => 'This Microsoft account has no Xbox profile yet. Sign in once at xbox.com to create one, then link again.',
            2148916235 => 'Xbox Live is not available in the country this account is registered in.',
            2148916236, 2148916237 => 'Xbox needs an adult verification on this account before it can be used here.',
            2148916238 => 'This is a child account. An adult must add it to a Microsoft family group first.',
            default => "Xbox Live refused the login (HTTP {$status}".($code ? ", code {$code}" : '').').',
        };
    }

    private function headers(array $identity, string $contract): array
    {
        return [
            'Authorization' => "XBL3.0 x={$identity['uhs']};{$identity['token']}",
            'x-xbl-contract-version' => $contract,
            'Accept-Language' => 'en-US',
        ];
    }

    private function xblGet(string $url, array $identity, string $contract): Response
    {
        try {
            return Http::withHeaders($this->headers($identity, $contract))->acceptJson()->timeout(60)->get($url);
        } catch (ConnectionException) {
            throw new ProviderException('Could not reach Xbox Live. Try again in a moment.');
        }
    }

    /** @return array<string, mixed> */
    private function checked(Response $response, string $what): array
    {
        if ($response->status() === 401) {
            throw new XboxUnauthorized('Xbox Live rejected the login token.');
        }

        if ($response->status() === 429) {
            throw new ProviderException('Xbox Live is rate limiting requests. Try again later.');
        }

        if ($response->failed()) {
            throw new ProviderException("Xbox Live could not {$what} (HTTP {$response->status()}).");
        }

        return $response->json() ?? [];
    }
}
