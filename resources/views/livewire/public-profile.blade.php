@php
    use App\Support\Format;

    $hours = number_format($profile['hours']);
    $ach = $profile['achievements'];
    $maxTop = max(1, (int) collect($profile['top'])->max('playtime_minutes'));
@endphp

@push('head')
    <meta name="robots" content="noindex, nofollow">
    <meta property="og:title" content="{{ $profile['handle'] }} on GameHub">
    <meta property="og:description" content="{{ number_format($profile['games']) }} games, {{ $hours }} hours played, {{ $profile['finished'] }} finished.">
@endpush

<div class="stack">
    <div class="page-head">
        <div>
            <h1 class="page-title">{{ $profile['handle'] }}</h1>
            <p class="page-sub">A GameHub library</p>
        </div>
    </div>

    <section class="tiles" aria-label="Summary">
        <div class="tile" style="--st: var(--playing)"><span class="tile-n">{{ $hours }}</span><span class="tile-l">hours played</span></div>
        <div class="tile" style="--st: var(--focus)"><span class="tile-n">{{ number_format($profile['games']) }}</span><span class="tile-l">games</span></div>
        <div class="tile" style="--st: var(--good)"><span class="tile-n">{{ $profile['finished'] }}</span><span class="tile-l">finished</span></div>
        @if ($ach['total'] > 0)
            <div class="tile" style="--st: #ff7ac6"><span class="tile-n">{{ number_format($ach['unlocked']) }}</span><span class="tile-l">of {{ number_format($ach['total']) }} achievements and trophies</span></div>
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

    @if ($profile['playing'])
        <section>
            <h2 class="section-title">Playing now</h2>
            <div class="minis">
                @foreach ($profile['playing'] as $entry)
                    @include('livewire.partials.mini-game', ['entry' => $entry])
                @endforeach
            </div>
        </section>
    @endif

    <div class="split-2">
        <section class="panel">
            <h2 class="section-title">Most played</h2>

            @forelse ($profile['top'] as $entry)
                @php($t = Format::duration((int) $entry->playtime_minutes))
                <div class="rank" style="--pc: {{ $entry->platform->color() }}; --w: {{ round($entry->playtime_minutes / $maxTop * 100) }}%">
                    <div class="rank-name"><span>{{ $entry->game->title }}</span><b>{{ $t[0] }} <small>{{ $t[1] }}</small></b></div>
                    <i></i>
                </div>
            @empty
                <p class="provider-note">No playtime yet.</p>
            @endforelse
        </section>

        <section>
            <h2 class="section-title">Recently finished</h2>

            @if ($profile['recentlyFinished'])
                <div class="minis minis-2">
                    @foreach ($profile['recentlyFinished'] as $entry)
                        @include('livewire.partials.mini-game', ['entry' => $entry])
                    @endforeach
                </div>
            @else
                <p class="provider-note">Nothing finished yet.</p>
            @endif
        </section>
    </div>
</div>
