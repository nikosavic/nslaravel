<div class="stack">
    <div class="page-head">
        <div>
            <h1 class="page-title">Duplicates</h1>
            <p class="page-sub">Games that appear twice under the same title. Pick the one to keep.</p>
        </div>

        <a class="btn btn-quiet" href="{{ route('games.library') }}">Back to library</a>
    </div>

    @if ($notice)
        <div class="notice" role="status">{{ $notice }}</div>
    @endif

    @forelse ($groups as $group)
        <section class="panel dupe" wire:key="dupe-{{ $group->first()['game']->id }}">
            <h2 class="section-title">{{ $group->first()['game']->title }}</h2>

            <div class="dupe-rows">
                @foreach ($group as $row)
                    @php($others = $group->reject(fn ($r) => $r['game']->id === $row['game']->id))

                    <div class="dupe-row" wire:key="dupe-row-{{ $row['game']->id }}">
                        <div class="dupe-cover">
                            @if ($row['game']->cover_url)
                                <img src="{{ $row['game']->cover_url }}" alt="" loading="lazy">
                            @endif
                        </div>

                        <div>
                            <strong>{{ $row['game']->title }}</strong>
                            <div class="game-meta">
                                <span>{{ implode(', ', $row['platforms']) }}</span>
                                <span>{{ $row['game']->igdb_id ? 'Matched on IGDB' : 'Not matched on IGDB' }}</span>
                            </div>
                        </div>

                        <div class="dupe-actions">
                            @foreach ($others as $other)
                                <button type="button" class="btn btn-quiet btn-sm"
                                        wire:click="merge({{ $row['game']->id }}, {{ $other['game']->id }})"
                                        wire:confirm="Merge into this entry and remove the other one?">
                                    Keep this one
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @empty
        <div class="empty">
            <h2>No duplicates</h2>
            <p>Every game in your library appears once. After linking another platform, check back here.</p>
        </div>
    @endforelse
</div>
