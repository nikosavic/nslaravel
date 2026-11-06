<?php

namespace App\Livewire;

use App\Enums\ProviderType;
use App\Jobs\SyncLinkedAccount;
use App\Models\LinkedAccount;
use App\Services\Igdb\IgdbClient;
use App\Services\Providers\Xbox\XboxLiveClient;
use App\Services\Providers\Exceptions\ProviderException;
use App\Services\Providers\PlaystationProvider;
use App\Services\Providers\SteamProvider;
use App\Services\Providers\XboxProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.gamehub')]
#[Title('Accounts')]
class Accounts extends Component
{
    public string $steamInput = '';

    public string $psnInput = '';

    public string $xboxInput = '';

    /** Shown while a Microsoft device login is waiting for you to enter the code. */
    public ?string $xboxCode = null;

    public ?string $xboxCodeUrl = null;

    public ?string $notice = null;

    public function linkSteam(): void
    {
        $this->validate(['steamInput' => ['required', 'string', 'max:200']]);

        try {
            $resolved = app(SteamProvider::class)->resolveAccount($this->steamInput);
        } catch (ProviderException $e) {
            $this->addError('steamInput', $e->getMessage());

            return;
        }

        $account = LinkedAccount::updateOrCreate(
            [
                'user_id' => Auth::id(),
                'provider' => ProviderType::Steam->value,
                'external_id' => $resolved->externalId,
            ],
            ['display_name' => $resolved->displayName],
        );

        SyncLinkedAccount::dispatch($account->id);

        $this->steamInput = '';
        $this->notice = "Linked {$resolved->displayName}. The first sync is queued."
            .($resolved->warning ? ' Heads up: '.$resolved->warning : '');
    }

    /** Links PlayStation, or re-logs an already linked account with a fresh NPSSO token (same account id, new tokens). */
    public function linkPlaystation(): void
    {
        $this->validate(['psnInput' => ['required', 'string', 'max:2000']]);

        try {
            $resolved = app(PlaystationProvider::class)->resolveAccount($this->psnInput);
        } catch (ProviderException $e) {
            $this->addError('psnInput', $e->getMessage());

            return;
        }

        $account = LinkedAccount::updateOrCreate(
            [
                'user_id' => Auth::id(),
                'provider' => ProviderType::Playstation->value,
                'external_id' => $resolved->externalId,
            ],
            [
                'display_name' => $resolved->displayName,
                'credentials' => $resolved->credentials,
            ],
        );

        SyncLinkedAccount::dispatch($account->id);

        $this->psnInput = ''; // the NPSSO is never kept, only the renewable tokens (encrypted)
        $this->notice = "Linked {$resolved->displayName}. The first sync is queued.";
    }

    /** Links Xbox through the user's own OpenXBL key; re-linking with a new key replaces the stored one. */
    public function linkXbox(): void
    {
        $this->validate(['xboxInput' => ['required', 'string', 'max:300']]);

        try {
            $resolved = app(XboxProvider::class)->resolveAccount($this->xboxInput);
        } catch (ProviderException $e) {
            $this->addError('xboxInput', $e->getMessage());

            return;
        }

        $account = LinkedAccount::updateOrCreate(
            [
                'user_id' => Auth::id(),
                'provider' => ProviderType::Xbox->value,
                'external_id' => $resolved->externalId,
            ],
            [
                'display_name' => $resolved->displayName,
                'credentials' => $resolved->credentials,
            ],
        );

        SyncLinkedAccount::dispatch($account->id);

        $this->xboxInput = '';
        $this->notice = "Linked {$resolved->displayName}. The first sync is queued.";
    }

    private function xboxLoginKey(): string
    {
        return 'xbox-device-login:'.Auth::id();
    }

    /** Step 1 of the direct Xbox login: ask Microsoft for a code. The device code itself stays on the server. */
    public function startXboxLogin(XboxLiveClient $xbox): void
    {
        try {
            $login = $xbox->startDeviceLogin();
        } catch (ProviderException $e) {
            $this->addError('xboxLogin', $e->getMessage());

            return;
        }

        Cache::put($this->xboxLoginKey(), $login + ['next_poll' => now()->addSeconds($login['interval'])->timestamp], $login['expires_in']);

        $this->xboxCode = $login['user_code'];
        $this->xboxCodeUrl = $login['verification_uri'];
        $this->resetErrorBag('xboxLogin');
    }

    /** Step 2, called by the page every few seconds while the code is showing. */
    public function checkXboxLogin(XboxLiveClient $xbox): void
    {
        $login = Cache::get($this->xboxLoginKey());

        if (! $login) {
            $this->cancelXboxLogin();
            $this->addError('xboxLogin', 'The code expired. Start again.');

            return;
        }

        if (now()->timestamp < $login['next_poll']) {
            return; // Microsoft asks to be polled no faster than its interval
        }

        try {
            $result = $xbox->pollDeviceLogin($login['device_code']);

            if ($result['status'] === 'ok') {
                $resolved = app(XboxProvider::class)->completeLogin($result['tokens']);
            }
        } catch (ProviderException $e) {
            $this->cancelXboxLogin();
            $this->addError('xboxLogin', $e->getMessage());

            return;
        }

        switch ($result['status']) {
            case 'pending':
            case 'slow_down':
                $login['next_poll'] = now()->addSeconds($login['interval'] + ($result['status'] === 'slow_down' ? 5 : 0))->timestamp;
                Cache::put($this->xboxLoginKey(), $login, max(60, $login['expires_in']));

                return;
            case 'declined':
                $this->cancelXboxLogin();
                $this->addError('xboxLogin', 'The login was declined on the Microsoft page.');

                return;
            case 'expired':
                $this->cancelXboxLogin();
                $this->addError('xboxLogin', 'The code expired. Start again.');

                return;
        }

        $account = LinkedAccount::updateOrCreate(
            ['user_id' => Auth::id(), 'provider' => ProviderType::Xbox->value, 'external_id' => $resolved->externalId],
            ['display_name' => $resolved->displayName, 'credentials' => $resolved->credentials],
        );

        SyncLinkedAccount::dispatch($account->id);

        $this->cancelXboxLogin();
        $this->notice = "Linked {$resolved->displayName}. The first sync is queued.";
    }

    public function cancelXboxLogin(): void
    {
        Cache::forget($this->xboxLoginKey());
        $this->xboxCode = null;
        $this->xboxCodeUrl = null;
    }

    public function syncNow(int $accountId): void
    {
        $account = $this->ownAccount($accountId);

        if ($account) {
            SyncLinkedAccount::dispatch($account->id);
            $this->notice = "Sync queued for {$account->display_name}.";
        }
    }

    public function unlink(int $accountId): void
    {
        // Imported library entries stay (they just lose their link to the account).
        $this->ownAccount($accountId)?->delete();
    }

    private function ownAccount(int $id): ?LinkedAccount
    {
        return LinkedAccount::where('user_id', Auth::id())->find($id);
    }

    public function render()
    {
        return view('livewire.accounts', [
            'accounts' => LinkedAccount::with('latestRun')
                ->where('user_id', Auth::id())
                ->orderBy('provider')
                ->get(),
            'steamConfigured' => filled(config('gamehub.steam.key')),
            'igdbConfigured' => app(IgdbClient::class)->isConfigured(),
            'xboxDirect' => app(XboxLiveClient::class)->isConfigured(),
        ]);
    }
}
