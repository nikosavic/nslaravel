<div class="stack">
    <div class="page-head">
        <div>
            <h1 class="page-title">Accounts</h1>
            <p class="page-sub">Link a platform and its games sync into your library.</p>
        </div>
    </div>

    @if ($notice)
        <div class="notice" role="status">{{ $notice }}</div>
    @endif

    @unless ($igdbConfigured)
        <div class="notice notice-warn">
            IGDB is not configured, so games from different platforms are not merged and covers come from the stores only.
            Add IGDB_CLIENT_ID and IGDB_CLIENT_SECRET to .env, then run <code>sail artisan gamehub:enrich</code>.
        </div>
    @endunless

    <div class="split">
        <div class="col">
            {{-- Steam --}}
            <section class="provider" style="--pv: {{ \App\Enums\ProviderType::Steam->color() }}">
                <h2>Link Steam</h2>
                <p class="provider-intro">Imports every game you own, with playtime.</p>

                @unless ($steamConfigured)
                    <p class="notice notice-warn" style="margin-top: 18px">STEAM_API_KEY is missing in .env. Get one at steamcommunity.com/dev/apikey.</p>
                @endunless

                <form wire:submit="linkSteam">
                    <input type="text" class="field" wire:model="steamInput" aria-label="Steam profile"
                           placeholder="SteamID64, profile URL or custom profile name">
                    <button type="submit" class="btn">Link and sync</button>
                </form>
                @error('steamInput') <p class="error">{{ $message }}</p> @enderror

                <p class="provider-note">
                    In Steam privacy settings, set "My profile" and "Game details" to Public. Otherwise Steam returns no games.
                </p>
            </section>

            {{-- PlayStation --}}
            <section class="provider" style="--pv: {{ \App\Enums\ProviderType::Playstation->color() }}">
                <h2>Link PlayStation</h2>
                <p class="provider-intro">Imports your PS5 and PS4 games, with playtime.</p>

                <form wire:submit="linkPlaystation">
                    <input type="password" class="field" wire:model="psnInput" autocomplete="off" spellcheck="false"
                           aria-label="NPSSO token" placeholder="Paste your NPSSO token">
                    <button type="submit" class="btn" wire:loading.attr="disabled" wire:target="linkPlaystation">
                        <span wire:loading.remove wire:target="linkPlaystation">Link and sync</span>
                        <span wire:loading wire:target="linkPlaystation">Logging in…</span>
                    </button>
                </form>
                @error('psnInput') <p class="error">{{ $message }}</p> @enderror

                <ol class="steps">
                    <li>Sign in at playstation.com in this browser.</li>
                    <li>
                        Open
                        <a href="https://ca.account.sony.com/api/v1/ssocookie" target="_blank" rel="noopener noreferrer">ca.account.sony.com/api/v1/ssocookie</a>
                        and copy the value after "npsso". Pasting the whole page works too.
                    </li>
                    <li>Paste it above. Use the same form again when PlayStation asks you to log in again.</li>
                </ol>

                <p class="provider-note">
                    The NPSSO token works like a password. It is used once to log in and is not saved. Only a renewable login is kept, encrypted.
                </p>
            </section>

            {{-- Xbox --}}
            <section class="provider" style="--pv: {{ \App\Enums\ProviderType::Xbox->color() }}">
                <h2>Link Xbox</h2>
                <p class="provider-intro">Imports the games you have played, when you last played them, your achievements and, where Xbox reports it, hours.</p>

                @if ($xboxDirect)
                    @if ($xboxCode)
                        <div class="devicecode" wire:poll.4s="checkXboxLogin">
                            <span class="devicecode-label">Enter this code at {{ preg_replace('~^https?://(www\.)?~', '', $xboxCodeUrl) }}</span>
                            <strong class="devicecode-code">{{ $xboxCode }}</strong>
                            <p class="provider-note">Sign in with the Microsoft account you play on and approve GameHub. This page notices by itself.</p>
                            <div class="hero-actions">
                                <a class="btn" href="{{ $xboxCodeUrl }}" target="_blank" rel="noopener noreferrer">Open the Microsoft page</a>
                                <button type="button" class="btn btn-quiet" wire:click="cancelXboxLogin">Cancel</button>
                            </div>
                        </div>
                    @else
                        <div class="hero-actions">
                            <button type="button" class="btn" wire:click="startXboxLogin" wire:loading.attr="disabled" wire:target="startXboxLogin">Link with Microsoft</button>
                        </div>
                    @endif
                    @error('xboxLogin') <p class="error">{{ $message }}</p> @enderror

                    <p class="provider-note">
                        GameHub talks to Microsoft and Xbox Live directly. Only a renewable login is kept, encrypted. No password is ever seen.
                    </p>
                @else
                    <ol class="steps">
                        <li>In the <a href="https://portal.azure.com/#view/Microsoft_AAD_RegisteredApps/ApplicationsListBlade" target="_blank" rel="noopener noreferrer">Azure portal</a>, open App registrations and choose New registration.</li>
                        <li>Name it GameHub. For account types pick "Accounts in any organizational directory and personal Microsoft accounts". Leave the redirect empty and register.</li>
                        <li>Under Authentication, set "Allow public client flows" to Yes and save.</li>
                        <li>Copy the Application (client) ID into <code>XBOX_CLIENT_ID=</code> in <code>.env</code>, then run <code>sail artisan config:clear</code>.</li>
                    </ol>
                    <p class="provider-note">It is free and done once. Then this panel offers "Link with Microsoft".</p>
                @endif

                <details class="alt" @if (! $xboxDirect) open @endif>
                    <summary>Use an OpenXBL key instead</summary>

                    <form wire:submit="linkXbox">
                        <input type="password" class="field" wire:model="xboxInput" autocomplete="off" spellcheck="false"
                               aria-label="OpenXBL API key" placeholder="Paste your OpenXBL API key">
                        <button type="submit" class="btn" wire:loading.attr="disabled" wire:target="linkXbox">
                            <span wire:loading.remove wire:target="linkXbox">Link and sync</span>
                            <span wire:loading wire:target="linkXbox">Checking…</span>
                        </button>
                    </form>
                    @error('xboxInput') <p class="error">{{ $message }}</p> @enderror

                    <p class="provider-note">
                        <a href="https://xbl.io" target="_blank" rel="noopener noreferrer">xbl.io</a> is a third-party service (it may be blocked on some networks). Games and achievements only, no hours.
                    </p>
                </details>
            </section>
        </div>

        {{-- Linked accounts --}}
        <section wire:poll.5s>
            <h2 class="section-title">Linked accounts</h2>

            <div class="acct-list">
                @forelse ($accounts as $account)
                    @php($run = $account->latestRun)

                    <div class="acct" wire:key="account-{{ $account->id }}" style="--pv: {{ $account->provider->color() }}">
                        <div>
                            <div class="acct-name">
                                {{ $account->provider->label() }}<small>{{ $account->display_name ?? $account->external_id }}</small>
                            </div>

                            <div class="acct-state">
                                @if ($run === null)
                                    <span class="chip"><i></i>Not synced yet</span>
                                @elseif ($run->status === 'running')
                                    <span class="chip chip-busy"><i></i>Syncing</span>
                                    <span>Fetching your games.</span>
                                @elseif ($run->status === 'success')
                                    <span class="chip chip-ok"><i></i>Synced</span>
                                    <span>
                                        Last sync {{ $run->finished_at?->diffForHumans() }}:
                                        {{ $run->games_seen }} games, {{ $run->created_count }} new, {{ $run->updated_count }} updated.
                                    </span>
                                @else
                                    <span class="chip chip-bad"><i></i>Needs attention</span>
                                @endif
                            </div>
                        </div>

                        <div class="acct-actions">
                            <button type="button" class="btn btn-quiet btn-sm" wire:click="syncNow({{ $account->id }})">Sync now</button>
                            <button type="button" class="btn btn-quiet btn-sm" wire:click="unlink({{ $account->id }})"
                                    wire:confirm="Unlink this account? Games already imported stay in your library.">Unlink</button>
                        </div>

                        @if ($run && $run->status === 'failed')
                            <p class="acct-error">Last sync failed: {{ $run->error }}</p>
                        @endif
                    </div>
                @empty
                    <p class="provider-note">No accounts linked yet.</p>
                @endforelse
            </div>

            <p class="footnote">
                Syncs run on the queue. If a sync never starts, run <code>bin/dev-workers.sh</code>.
            </p>
        </section>
    </div>
</div>
