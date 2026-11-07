@php
    use App\Support\Format;

    $hours = number_format($profile['hours']);
    $ach = $profile['achievements'];
    $maxTop = max(1, (int) collect($profile['top'])->max('playtime_minutes'));
    $playing = $profile['playing'][0] ?? null;
    $byStatus = collect($profile['statuses'])->pluck('count', 'key');
    $trophyWord = fn ($entry) => $entry->platform->family === 'playstation' ? 'trophies' : 'achievements';
@endphp

@push('head')
    <meta name="description" content="{{ number_format($profile['games']) }} games and {{ $hours }} hours played. Ratings, trophies and what I am playing now.">
    <meta property="og:title" content="{{ ucfirst($profile['handle']) }} on GameHub">
    <meta property="og:description" content="{{ number_format($profile['games']) }} games, {{ $hours }} hours played, {{ $profile['finished'] }} finished.">
@endpush

<div class="stack">
    <div class="page-head">
        <div>
            <h1 class="page-title">{{ ucfirst($profile['handle']) }}</h1>
            <p class="page-sub">Games I play, and have played</p>
        </div>

        <nav class="profile-links" aria-label="Elsewhere">
            <a class="social" href="https://www.linkedin.com/in/nikola-savic-74a1aa26b/" target="_blank" rel="noopener noreferrer">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M8 11v5M8 8v.01M12 16v-5M12 13a2.5 2.5 0 0 1 5 0v3"/></svg>
                LinkedIn
            </a>
            <a class="social" href="https://www.instagram.com/iamnikosavic" target="_blank" rel="noopener noreferrer">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M17.5 6.5v.01"/></svg>
                Instagram
            </a>
        </nav>
    </div>

    {{-- Playing now --}}
    @if ($playing)
        @php
            $pg = $playing->game;
            $pt = $playing->playtimeParts();
        @endphp

        <section class="hero hero-public" aria-label="Playing now">
            <div @class(['hero-bg', 'is-empty' => ! $pg->cover_url]) aria-hidden="true">
                @if ($pg->cover_url)
                    <img src="{{ $pg->cover_url }}" alt="">
                @endif
            </div>

            <div class="hero-copy">
                <span class="tag-playing slant"><i></i>Playing now</span>

                <h2 class="hero-title">{{ $pg->title }}</h2>

                <div class="facts">
                    <span class="plat" style="--pc: {{ $playing->platform->color() }}"><i></i>{{ $playing->platform->name }}</span>
                    @if ($pt)
                        <span><strong>{{ $pt[0] }}</strong> {{ $pt[1] }} played</span>
                    @endif
                    @if ($playing->achievements_total > 0)
                        <span><strong>{{ $playing->achievements_unlocked }}</strong> of {{ $playing->achievements_total }} {{ $trophyWord($playing) }}</span>
                    @endif
                </div>
            </div>

            <div class="hero-art">
                <div class="frame art" style="--st: {{ \App\Enums\GameStatus::Playing->color() }}">
                    @if ($pg->cover_url)
                        <img class="fill" src="{{ $pg->cover_url }}" alt="">
                        <img class="main" src="{{ $pg->cover_url }}" alt="">
                    @else
                        <div class="art-empty" aria-hidden="true">{{ mb_strtoupper(mb_substr($pg->title, 0, 1)) }}</div>
                    @endif
                </div>
            </div>
        </section>
    @endif

    <section class="tiles" aria-label="Summary">
        <div class="tile" style="--st: var(--playing)"><span class="tile-n">{{ $hours }}</span><span class="tile-l">hours played</span></div>
        <div class="tile" style="--st: var(--focus)"><span class="tile-n">{{ number_format($profile['games']) }}</span><span class="tile-l">games</span></div>
        <div class="tile" style="--st: var(--good)"><span class="tile-n">{{ $profile['finished'] }}</span><span class="tile-l">finished</span></div>
        @if ($ach['total'] > 0)
            <div class="tile" style="--st: #ff7ac6"><span class="tile-n">{{ number_format($ach['unlocked']) }}</span><span class="tile-l">of {{ number_format($ach['total']) }} trophies and achievements</span></div>
        @endif
        @if ($profile['platinums'] > 0)
            <div class="tile" style="--st: #bfe0ff"><span class="tile-n">{{ $profile['platinums'] }}</span><span class="tile-l">{{ $profile['platinums'] === 1 ? 'platinum trophy' : 'platinum trophies' }}</span></div>
        @endif
        @if ($profile['avgRating'] !== null)
            <div class="tile" style="--st: var(--playing)"><span class="tile-n">{{ rtrim(rtrim(number_format($profile['avgRating'], 1), '0'), '.') }}<small>/10</small></span><span class="tile-l">average rating across {{ $profile['ratedCount'] }} {{ $profile['ratedCount'] === 1 ? 'game' : 'games' }}</span></div>
        @endif
    </section>

    @if ($profile['games'] > 0)
        <div class="spectrum" aria-hidden="true">
            @foreach ($profile['statuses'] as $s)
                <i style="--st: {{ $s['color'] }}; --n: {{ $s['count'] }}" title="{{ $s['label'] }}: {{ $s['count'] }}"></i>
            @endforeach
        </div>

        <ul class="legend">
            @foreach ($profile['statuses'] as $s)
                <li style="--st: {{ $s['color'] }}"><i></i>{{ $s['label'] }} <b>{{ $s['count'] }}</b></li>
            @endforeach
        </ul>
    @endif

    {{-- Highlights --}}
    @if ($profile['top'] || $profile['topRated'])
        <div class="split-2">
            <section class="panel">
                <h2 class="section-title">Most played</h2>

                @forelse ($profile['top'] as $entry)
                    @php
                        $t = Format::duration((int) $entry->playtime_minutes);
                    @endphp
                    <div class="rank" style="--pc: {{ $entry->platform->color() }}; --w: {{ round($entry->playtime_minutes / $maxTop * 100) }}%">
                        <div class="rank-name"><span>{{ $entry->game->title }}</span><b>{{ $t[0] }} <small>{{ $t[1] }}</small></b></div>
                        <i></i>
                    </div>
                @empty
                    <p class="provider-note">No playtime yet.</p>
                @endforelse
            </section>

            <section class="panel">
                <h2 class="section-title">Top rated</h2>

                @forelse ($profile['topRated'] as $entry)
                    <div class="rank rank-rate" style="--pc: var(--playing); --w: {{ (int) $entry->rating * 10 }}%">
                        <div class="rank-name"><span>{{ $entry->game->title }}</span><b>★ {{ $entry->rating }} <small>/10</small></b></div>
                        <i></i>
                    </div>
                @empty
                    <p class="provider-note">No ratings yet.</p>
                @endforelse
            </section>
        </div>
    @endif

    @if ($profile['recentlyFinished'])
        <section>
            <h2 class="section-title">Recently finished</h2>

            <div class="minis">
                @foreach ($profile['recentlyFinished'] as $entry)
                    @include('livewire.partials.mini-game', ['entry' => $entry])
                @endforeach
            </div>
        </section>
    @endif

    {{-- Every game --}}
    <section aria-label="All games" id="all-games">
        <h2 class="section-title">All games</h2>

        <div class="tabs" role="group" aria-label="Filter by status">
            <button type="button" class="tab" wire:click="$set('status', '')" aria-pressed="{{ $status === '' ? 'true' : 'false' }}">
                <i class="dot"></i>All<span class="n">{{ $profile['games'] }}</span>
            </button>

            @foreach ($statuses as $s)
                @if (($byStatus[$s->value] ?? 0) > 0)
                    <button type="button" class="tab" wire:key="tab-{{ $s->value }}" style="--st: {{ $s->color() }}"
                            wire:click="$set('status', '{{ $s->value }}')" aria-pressed="{{ $status === $s->value ? 'true' : 'false' }}">
                        <i class="dot"></i>{{ $s->label() }}<span class="n">{{ $byStatus[$s->value] }}</span>
                    </button>
                @endif
            @endforeach
        </div>

        <div class="toolbar">
            <div class="search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input class="field" type="search" wire:model.live.debounce.300ms="search" placeholder="Search games" aria-label="Search games">
            </div>

            <label class="sel">
                <span class="sr-only">Platform</span>
                <select class="field" wire:model.live="platform">
                    <option value="">All platforms</option>
                    @foreach ($platforms as $p)
                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="sel">
                <span class="sr-only">Sort by</span>
                <select class="field" wire:model.live="sort">
                    <option value="recent">Recently played</option>
                    <option value="rating">Highest rated</option>
                    <option value="playtime">Most played</option>
                    <option value="trophies">Most trophies</option>
                    <option value="title">Title A to Z</option>
                </select>
            </label>
        </div>

        @if ($entries->isNotEmpty())
            <div class="grid">
                @foreach ($entries as $entry)
                    @php
                        $game = $entry->game;
                        $time = $entry->playtimeParts();
                        $pct = $entry->achievementPercent();
                    @endphp

                    <article class="game" wire:key="entry-{{ $entry->id }}" data-status="{{ $entry->status->value }}" style="--st: {{ $entry->status->color() }}">
                        <div class="art">
                            @if ($game->cover_url)
                                <img class="fill" src="{{ $game->cover_url }}" alt="" loading="lazy">
                                <img class="main" src="{{ $game->cover_url }}" alt="{{ $game->title }}" loading="lazy">
                            @else
                                <div class="art-empty" aria-hidden="true">{{ mb_strtoupper(mb_substr($game->title, 0, 1)) }}</div>
                            @endif

                            <span class="art-tag slant" style="--pc: {{ $entry->platform->color() }}"><i></i>{{ $entry->platform->name }}</span>

                            @if ($entry->rating)
                                <span class="art-rate" title="Rated {{ $entry->rating }} out of 10">★ {{ $entry->rating }}<small>/10</small></span>
                            @endif
                        </div>

                        <div class="game-body">
                            <h3 class="game-title">{{ $game->title }}</h3>

                            <div class="game-meta">
                                <span class="status-chip"><i></i>{{ $entry->status->label() }}</span>

                                @if ($time)
                                    <span>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                                        <span><b>{{ $time[0] }}</b> {{ $time[1] }}</span>
                                    </span>
                                @endif

                                @if ($entry->achievements_total > 0)
                                    <span @class(['ach', 'is-plat' => $entry->hasPlatinum(), 'is-full' => $entry->achievements_unlocked >= $entry->achievements_total])
                                          title="{{ $entry->achievements_unlocked }} of {{ $entry->achievements_total }} {{ $trophyWord($entry) }}">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 4h8v6a4 4 0 0 1-8 0V4ZM8 6H5a3 3 0 0 0 3 4M16 6h3a3 3 0 0 1-3 4M12 14v4M9 20h6"/></svg>
                                        <b>{{ $entry->achievements_unlocked }}</b>/{{ $entry->achievements_total }}
                                        @if ($entry->hasPlatinum())<em class="ach-tag">Platinum</em>@endif
                                    </span>
                                @endif
                            </div>

                            @if ($pct !== null)
                                <div class="meter" style="--w: {{ $pct }}%" role="img" aria-label="{{ $pct }}% of {{ $trophyWord($entry) }} earned"><i></i></div>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($hasMore)
                <div class="more-wrap">
                    <button type="button" class="btn btn-quiet" wire:click="more">Show more games</button>
                </div>
            @endif
        @else
            <div class="empty">
                @if ($profile['games'] === 0)
                    <h2>No games yet</h2>
                    <p>Check back soon.</p>
                @else
                    <h2>No games match</h2>
                    <p>Try a different search, or clear the filters to see everything.</p>
                    @if ($filtering)
                        <div class="empty-actions">
                            <button type="button" class="btn" wire:click="clearFilters">Clear filters</button>
                        </div>
                    @endif
                @endif
            </div>
        @endif
    </section>
</div>
