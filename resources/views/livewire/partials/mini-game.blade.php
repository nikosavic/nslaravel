@php($game = $entry->game)

<article class="mini" style="--st: {{ $entry->status->color() }}">
    <div class="mini-art art">
        @if ($game->cover_url)
            <img class="fill" src="{{ $game->cover_url }}" alt="" loading="lazy">
            <img class="main" src="{{ $game->cover_url }}" alt="" loading="lazy">
        @else
            <div class="art-empty" aria-hidden="true">{{ mb_strtoupper(mb_substr($game->title, 0, 1)) }}</div>
        @endif
    </div>
    <div class="mini-body">
        <h3>{{ $game->title }}</h3>
        <span>{{ $entry->platform->name }}@if ($entry->playtime_minutes > 0), {{ \App\Support\Format::durationText((int) $entry->playtime_minutes) }}@endif</span>
    </div>
</article>
