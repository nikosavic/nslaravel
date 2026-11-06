@php
    use App\Support\Format;

    $hours = Format::duration($backlog['minutes']);
@endphp

<div class="stack">
    <div class="page-head">
        <div>
            <h1 class="page-title">Play next</h1>
            <p class="page-sub">Three games worth your evening, with the reason for each.</p>
        </div>

        <button type="button" class="btn" wire:click="shuffle">Shuffle</button>
    </div>

    <section class="tiles tiles-2" aria-label="Backlog">
        <div class="tile" style="--st: #8aa2ff">
            <span class="tile-n">{{ $hours[0] }}</span>
            <span class="tile-l">{{ $hours[1] }} left across your backlog, playing and on-hold games</span>
        </div>
        <div class="tile" style="--st: var(--faint)">
            <span class="tile-n">{{ $backlog['games'] - $backlog['unknown'] }}<small>/{{ $backlog['games'] }}</small></span>
            <span class="tile-l">games have a known length{{ $backlog['unknown'] > 0 ? '. The rest are left out of the total.' : '.' }}</span>
        </div>
    </section>

    <div class="toolbar">
        <label class="sel">
            <span class="sr-only">Mood</span>
            <select class="field" wire:model.live="mode">
                @foreach ($modes as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="sel">
            <span class="sr-only">Length</span>
            <select class="field" wire:model.live="length">
                @foreach ($lengths as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="sel">
            <span class="sr-only">Platform</span>
            <select class="field" wire:model.live="platform">
                <option value="">All platforms</option>
                @foreach ($platforms as $p)
                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                @endforeach
            </select>
        </label>
    </div>

    @forelse ($picks as $i => $pick)
        @php
            $entry = $pick['entry'];
            $game = $entry->game;
            $beat = $game->ttb_main_minutes ? Format::durationText($game->ttb_main_minutes) : null;
        @endphp

        <article class="pick" wire:key="pick-{{ $entry->id }}" style="--st: {{ $entry->status->color() }}">
            <div class="pick-art art">
                @if ($game->cover_url)
                    <img class="fill" src="{{ $game->cover_url }}" alt="" loading="lazy">
                    <img class="main" src="{{ $game->cover_url }}" alt="" loading="lazy">
                @else
                    <div class="art-empty" aria-hidden="true">{{ mb_strtoupper(mb_substr($game->title, 0, 1)) }}</div>
                @endif
            </div>

            <div class="pick-body">
                <span class="pick-rank">{{ $i + 1 }}</span>
                <h2 class="pick-title">{{ $game->title }}</h2>

                <div class="facts">
                    <span class="plat" style="--pc: {{ $entry->platform->color() }}"><i></i>{{ $entry->platform->name }}</span>
                    <span>{{ $entry->status->label() }}</span>
                    @if ($beat)
                        <span>About {{ $beat }} to beat</span>
                    @endif
                </div>

                <ul class="reasons">
                    @foreach ($pick['reasons'] as $reason)
                        <li>{{ $reason }}</li>
                    @endforeach
                </ul>

                <div class="hero-actions">
                    @if ($entry->status !== \App\Enums\GameStatus::Playing)
                        <button type="button" class="btn" wire:click="start({{ $entry->id }})">Start playing</button>
                    @endif
                </div>
            </div>
        </article>
    @empty
        <div class="empty">
            <h2>Nothing fits</h2>
            <p>No game in your backlog matches those choices. Loosen the length or platform, or add games to your backlog.</p>
        </div>
    @endforelse

    @if ($backlog['unknown'] > 0)
        <p class="footnote">Lengths come from IGDB. Run <code>sail artisan gamehub:time-to-beat</code> to fetch the missing ones.</p>
    @endif
</div>
