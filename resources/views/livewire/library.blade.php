<div class="stack" x-data="{ add: false }">
    @php
        $statuses = \App\Enums\GameStatus::cases();
        $byStatus = $this->counts['byStatus'];
        $total = $this->counts['total'];
        $hours = $this->counts['hours'];
    @endphp

    <div class="page-head">
        <div>
            <h1 class="page-title">Library</h1>
            <p class="page-sub">
                {{ number_format($total) }} {{ $total === 1 ? 'game' : 'games' }} and {{ number_format($hours) }} {{ $hours === 1 ? 'hour' : 'hours' }} played
            </p>
        </div>

        <button type="button" class="btn" @click="add = ! add" :aria-expanded="add.toString()" aria-controls="add-game">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            Add game
        </button>
    </div>

    @if ($this->duplicateCount > 0)
        <div class="notice">
            {{ $this->duplicateCount }} {{ $this->duplicateCount === 1 ? 'game appears' : 'games appear' }} twice in your library.
            <a href="{{ route('games.duplicates') }}">Review and merge</a>
        </div>
    @endif

    {{-- Add a game by hand --}}
    <form id="add-game" wire:submit="addGame" class="panel" x-show="add" x-cloak>
        <div class="add-grid">
            <div>
                <label class="label" for="new-title">Title</label>
                <input id="new-title" class="field" type="text" wire:model="newTitle" placeholder="Game title">
                @error('newTitle') <p class="error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="new-platform">Platform</label>
                <div class="sel">
                    <select id="new-platform" class="field" wire:model="newPlatformId">
                        <option value="">Choose a platform</option>
                        @foreach ($this->platforms as $p)
                            <option value="{{ $p->id }}">{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                @error('newPlatformId') <p class="error">Choose a platform.</p> @enderror
            </div>

            <div>
                <label class="label" for="new-status">Status</label>
                <div class="sel">
                    <select id="new-status" class="field" wire:model="newStatus">
                        @foreach ($statuses as $s)
                            <option value="{{ $s->value }}">{{ $s->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <button type="submit" class="btn">Add to library</button>
        </div>
    </form>

    {{-- Now playing --}}
    @if ($hero)
        @php
            $heroGame = $hero->game;
            $heroTime = $hero->playtimeParts();
        @endphp

        <section class="hero" aria-label="Now playing">
            <div @class(['hero-bg', 'is-empty' => ! $heroGame->cover_url]) aria-hidden="true">
                @if ($heroGame->cover_url)
                    <img src="{{ $heroGame->cover_url }}" alt="">
                @endif
            </div>

            <div class="hero-copy">
                <span class="tag-playing slant"><i></i>Now playing</span>

                <h2 class="hero-title">{{ $heroGame->title }}</h2>

                <div class="facts">
                    <span class="plat" style="--pc: {{ $hero->platform->color() }}"><i></i>{{ $hero->platform->name }}</span>
                    @if ($heroTime)
                        <span><strong>{{ $heroTime[0] }}</strong> {{ $heroTime[1] }} played</span>
                    @endif
                    @if ($hero->last_played_at)
                        <span>Last played {{ $hero->last_played_at->diffForHumans() }}</span>
                    @endif
                </div>

                <div class="hero-actions">
                    <button type="button" class="btn" wire:click="setStatus({{ $hero->id }}, 'finished')">Mark finished</button>
                    <button type="button" class="btn btn-quiet" wire:click="setStatus({{ $hero->id }}, 'on_hold')">Put on hold</button>
                    @if ($morePlaying > 0)
                        <button type="button" class="btn btn-quiet" wire:click="$set('status', 'playing')">{{ $morePlaying }} more in progress</button>
                    @endif
                </div>
            </div>

            <div class="hero-art">
                <div class="frame art" style="--st: {{ \App\Enums\GameStatus::Playing->color() }}">
                    @if ($heroGame->cover_url)
                        <img class="fill" src="{{ $heroGame->cover_url }}" alt="">
                        <img class="main" src="{{ $heroGame->cover_url }}" alt="">
                    @else
                        <div class="art-empty" aria-hidden="true">{{ mb_strtoupper(mb_substr($heroGame->title, 0, 1)) }}</div>
                    @endif
                </div>
            </div>
        </section>
    @endif

    {{-- Filters --}}
    <section aria-label="Your games">
        @if ($total > 0)
            <div class="spectrum" aria-hidden="true">
                @foreach ($statuses as $s)
                    @if (($byStatus[$s->value] ?? 0) > 0)
                        <i style="--st: {{ $s->color() }}; --n: {{ $byStatus[$s->value] }}" title="{{ $s->label() }}"></i>
                    @endif
                @endforeach
            </div>
        @endif

        <div class="tabs" role="group" aria-label="Filter by status">
            <button type="button" class="tab" wire:click="$set('status', '')" aria-pressed="{{ $status === '' ? 'true' : 'false' }}">
                <i class="dot"></i>All<span class="n">{{ $total }}</span>
            </button>

            @foreach ($statuses as $s)
                <button type="button" class="tab" wire:key="tab-{{ $s->value }}" style="--st: {{ $s->color() }}"
                        wire:click="$set('status', '{{ $s->value }}')" aria-pressed="{{ $status === $s->value ? 'true' : 'false' }}">
                    <i class="dot"></i>{{ $s->label() }}<span class="n">{{ $byStatus[$s->value] ?? 0 }}</span>
                </button>
            @endforeach
        </div>

        <div class="toolbar">
            <div class="search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input class="field" type="search" wire:model.live.debounce.300ms="search" placeholder="Search your library" aria-label="Search your library">
            </div>

            <label class="sel">
                <span class="sr-only">Platform</span>
                <select class="field" wire:model.live="platform">
                    <option value="">All platforms</option>
                    @foreach ($this->platforms as $p)
                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="sel">
                <span class="sr-only">Sort by</span>
                <select class="field" wire:model.live="sort">
                    <option value="recent">Recently played</option>
                    <option value="playtime">Most played</option>
                    <option value="title">Title A to Z</option>
                    <option value="added">Recently added</option>
                </select>
            </label>
        </div>

        {{-- Games --}}
        @if ($entries->isNotEmpty())
            <div class="grid">
                @foreach ($entries as $entry)
                    @php
                        $game = $entry->game;
                        $time = $entry->playtimeParts();
                    @endphp

                    <article class="game" wire:key="entry-{{ $entry->id }}" data-status="{{ $entry->status->value }}"
                             style="--st: {{ $entry->status->color() }}">
                        <div class="art">
                            @if ($game->cover_url)
                                <img class="fill" src="{{ $game->cover_url }}" alt="" loading="lazy">
                                <img class="main" src="{{ $game->cover_url }}" alt="" loading="lazy">
                            @else
                                <div class="art-empty" aria-hidden="true">{{ mb_strtoupper(mb_substr($game->title, 0, 1)) }}</div>
                            @endif

                            <span class="art-tag slant" style="--pc: {{ $entry->platform->color() }}"><i></i>{{ $entry->platform->name }}</span>

                            <button type="button" class="icon-btn" wire:click="remove({{ $entry->id }})"
                                    wire:confirm="Remove this game from your library?" aria-label="Remove {{ $game->title }} from your library">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                            </button>
                        </div>

                        <div class="game-body">
                            <h3 class="game-title">{{ $game->title }}</h3>

                            <div class="game-meta">
                                @if ($time)
                                    <span>
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                                        <span><b>{{ $time[0] }}</b> {{ $time[1] }}</span>
                                    </span>
                                @endif

                                @if ($game->ttb_main_minutes && ! $entry->status->isFinal())
                                    <span>About {{ \App\Support\Format::durationText($game->ttb_main_minutes) }} to beat</span>
                                @endif

                                @if ($entry->achievements_total > 0)
                                    <span @class(['ach', 'is-plat' => $entry->hasPlatinum(), 'is-full' => $entry->achievements_unlocked >= $entry->achievements_total])
                                          title="{{ $entry->hasPlatinum() ? 'Platinum earned. ' : '' }}{{ $entry->achievements_unlocked }} of {{ $entry->achievements_total }} {{ $entry->platform->family === 'playstation' ? 'trophies' : 'achievements' }}">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 4h8v6a4 4 0 0 1-8 0V4ZM8 6H5a3 3 0 0 0 3 4M16 6h3a3 3 0 0 1-3 4M12 14v4M9 20h6"/></svg>
                                        <b>{{ $entry->achievements_unlocked }}</b>/{{ $entry->achievements_total }}
                                        @if ($entry->hasPlatinum())<em class="ach-tag">Platinum</em>@endif
                                    </span>
                                @endif

                                @if ($entry->last_played_at)
                                    <span>Played {{ $entry->last_played_at->diffForHumans() }}</span>
                                @elseif (! $time)
                                    <span>Not played yet</span>
                                @endif
                            </div>

                            <div class="game-actions">
                                <label class="pill">
                                    <span class="sr-only">Status of {{ $game->title }}</span>
                                    <select wire:change="setStatus({{ $entry->id }}, $event.target.value)">
                                        @foreach ($statuses as $s)
                                            <option value="{{ $s->value }}" @selected($entry->status === $s)>{{ $s->label() }}</option>
                                        @endforeach
                                    </select>
                                </label>

                                <label class="pill pill-rate">
                                    <span class="sr-only">Your rating of {{ $game->title }}</span>
                                    <select wire:change="setRating({{ $entry->id }}, $event.target.value)">
                                        <option value="">Rate</option>
                                        @for ($i = 1; $i <= 10; $i++)
                                            <option value="{{ $i }}" @selected((int) $entry->rating === $i)>★ {{ $i }}</option>
                                        @endfor
                                    </select>
                                </label>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($entries->hasPages())
                <nav class="pager" aria-label="Pages">
                    <button type="button" class="btn btn-quiet btn-sm" wire:click="previousPage" x-on:click="window.scrollTo({ top: 0 })"
                            @disabled($entries->onFirstPage())>Previous</button>
                    <span>Page {{ $entries->currentPage() }} of {{ $entries->lastPage() }}</span>
                    <button type="button" class="btn btn-quiet btn-sm" wire:click="nextPage" x-on:click="window.scrollTo({ top: 0 })"
                            @disabled(! $entries->hasMorePages())>Next</button>
                </nav>
            @endif
        @else
            <div class="empty">
                @if ($total === 0)
                    <h2>Your library is empty</h2>
                    <p>Link a platform to import your games, or add one by hand.</p>
                    <div class="empty-actions">
                        <a class="btn" href="{{ route('games.accounts') }}">Link a platform</a>
                        <button type="button" class="btn btn-quiet" @click="add = true; window.scrollTo({ top: 0 })">Add a game</button>
                    </div>
                @else
                    <h2>No games match</h2>
                    <p>Try a different search, or clear the filters to see everything.</p>
                    <div class="empty-actions">
                        <button type="button" class="btn" wire:click="clearFilters">Clear filters</button>
                    </div>
                @endif
            </div>
        @endif
    </section>
</div>
