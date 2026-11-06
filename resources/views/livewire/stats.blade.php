@php
    use App\Support\Format;

    $total = Format::duration($report['minutes']);
    $max = max(1, collect($report['series'])->max('minutes'));
    $bars = count($report['series']);
    $labelEvery = $bars > 16 ? (int) ceil($bars / 8) : 1;
@endphp

<div class="stack">
    <div class="page-head">
        <div>
            <h1 class="page-title">Stats</h1>
            <p class="page-sub">{{ $report['period']->label() }}: {{ $report['start']->format('j M Y') }} to {{ $report['end']->format('j M Y') }}</p>
        </div>

        <a class="btn btn-quiet" href="{{ route('games.export.stats', ['period' => $report['period']->value]) }}">Download CSV</a>
    </div>

    <div class="tabs" role="group" aria-label="Period">
        @foreach ($periods as $p)
            <button type="button" class="tab" wire:key="period-{{ $p->value }}" wire:click="setPeriod('{{ $p->value }}')"
                    aria-pressed="{{ $report['period'] === $p ? 'true' : 'false' }}">{{ $p->short() }}</button>
        @endforeach
    </div>

    @unless ($report['hasHistory'])
        <div class="notice">
            Stats build up from your syncs, because Steam and PlayStation only report a running total per game.
            Link an account and let it sync; playtime from today onwards shows up here.
        </div>
    @endunless

    <section class="tiles" aria-label="Summary">
        <div class="tile" style="--st: var(--playing)">
            <span class="tile-n">{{ $total[0] }}</span>
            <span class="tile-l">{{ $total[1] }} played
                @if ($change !== null)
                    <b class="delta {{ $change >= 0 ? 'is-up' : 'is-down' }}">{{ $change >= 0 ? '+' : '' }}{{ $change }}%</b> on the period before
                @endif
            </span>
        </div>
        <div class="tile" style="--st: var(--focus)">
            <span class="tile-n">{{ $report['gamesPlayed'] }}</span>
            <span class="tile-l">{{ $report['gamesPlayed'] === 1 ? 'game' : 'games' }} played</span>
        </div>
        <div class="tile" style="--st: var(--good)">
            <span class="tile-n">{{ $report['finished'] }}</span>
            <span class="tile-l">finished</span>
        </div>
        <div class="tile" style="--st: #ff7ac6">
            <span class="tile-n">{{ $report['activeDays'] }}</span>
            <span class="tile-l">{{ $report['activeDays'] === 1 ? 'day' : 'days' }} with play</span>
        </div>
        <div class="tile" style="--st: #b49bff">
            <span class="tile-n">{{ $report['added'] }}</span>
            <span class="tile-l">added to the library</span>
        </div>
    </section>

    <section class="panel">
        <h2 class="section-title">Hours played</h2>

        <div class="chart" role="img" aria-label="Hours played per {{ $report['grain'] }}" style="--cols: {{ $bars }}">
            @foreach ($report['series'] as $i => $bucket)
                @php($hours = round($bucket['minutes'] / 60, 1))
                <div class="col" title="{{ $bucket['label'] }}: {{ $hours }} h">
                    <span class="col-v">{{ $bucket['minutes'] > 0 && $bars <= 14 ? $hours : '' }}</span>
                    <i style="--h: {{ $bucket['minutes'] > 0 ? max(3, round($bucket['minutes'] / $max * 100)) : 0 }}%"></i>
                    <span class="col-l">{{ $i % $labelEvery === 0 ? $bucket['label'] : '' }}</span>
                </div>
            @endforeach
        </div>
    </section>

    <div class="split-3">
        <section class="panel">
            <h2 class="section-title">Most played</h2>

            @forelse ($report['top'] as $row)
                @php($t = Format::duration($row['minutes']))
                <div class="rank" style="--pc: {{ $row['entry']->platform->color() }}; --w: {{ round($row['minutes'] / $report['top'][0]['minutes'] * 100) }}%">
                    <div class="rank-name"><span>{{ $row['entry']->game->title }}</span><b>{{ $t[0] }} <small>{{ $t[1] }}</small></b></div>
                    <i></i>
                </div>
            @empty
                <p class="provider-note">Nothing played in this stretch yet.</p>
            @endforelse
        </section>

        <section class="panel">
            <h2 class="section-title">By platform</h2>

            @forelse ($report['platforms'] as $row)
                @php($t = Format::duration($row['minutes']))
                <div class="rank" style="--pc: {{ $row['color'] }}; --w: {{ round($row['minutes'] / $report['platforms'][0]['minutes'] * 100) }}%">
                    <div class="rank-name"><span>{{ $row['name'] }}</span><b>{{ $t[0] }} <small>{{ $t[1] }}</small></b></div>
                    <i></i>
                </div>
            @empty
                <p class="provider-note">Nothing played in this stretch yet.</p>
            @endforelse
        </section>

        <section class="panel">
            <h2 class="section-title">By genre</h2>

            @forelse ($report['genres'] as $row)
                @php($t = Format::duration($row['minutes']))
                <div class="rank" style="--pc: #b49bff; --w: {{ round($row['minutes'] / $report['genres'][0]['minutes'] * 100) }}%">
                    <div class="rank-name"><span>{{ $row['name'] }}</span><b>{{ $t[0] }} <small>{{ $t[1] }}</small></b></div>
                    <i></i>
                </div>
            @empty
                <p class="provider-note">Genres come from IGDB. Add the IGDB keys in .env and run <code>sail artisan gamehub:enrich</code>.</p>
            @endforelse

            @if ($report['genres'])
                <p class="provider-note">A game with several genres counts toward each of them.</p>
            @endif
        </section>
    </div>

    <p class="footnote">
        Time is credited to the day a sync first sees it, and history starts at your first sync, so older play is not counted.
    </p>
</div>
