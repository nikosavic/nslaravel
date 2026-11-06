<div class="stack">
    <div class="page-head">
        <div>
            <h1 class="page-title">Profile</h1>
            <p class="page-sub">Share your library as a link or as an image.</p>
        </div>
    </div>

    @if ($notice)
        <div class="notice" role="status">{{ $notice }}</div>
    @endif

    <div class="split">
        <section class="panel">
            <h2 class="section-title">Public profile</h2>

            <form wire:submit="save" class="stack-sm">
                <label class="check">
                    <input type="checkbox" wire:model="isPublic"> Make my profile public
                </label>

                <div>
                    <label class="label" for="slug">Your link</label>
                    <div class="slug-field">
                        <span>{{ url('/games/u') }}/</span>
                        <input id="slug" class="field" type="text" wire:model="slug" autocomplete="off" spellcheck="false" placeholder="niko">
                    </div>
                    @error('slug') <p class="error">{{ $message }}</p> @enderror
                </div>

                <button type="submit" class="btn">Save</button>
            </form>

            @if ($publicUrl)
                <p class="provider-note">Live at <a href="{{ $publicUrl }}" target="_blank" rel="noopener">{{ $publicUrl }}</a></p>
            @endif

            <p class="provider-note">
                A public page shows your link name, how many games and hours you have, your status mix, what you are playing,
                what you finished lately and your most played games. It never shows your email, linked account names, notes,
                ratings or when you played. Search engines are asked not to list it, and turning it off returns a 404.
            </p>
        </section>

        <section class="panel" wire:ignore>
            <h2 class="section-title">Share card</h2>

            <canvas id="share-card" class="share-canvas" width="1200" height="630" role="img"
                    aria-label="Preview of your share card"></canvas>

            <div class="hero-actions">
                <button type="button" class="btn" id="share-download">Download PNG</button>
            </div>

            <p class="provider-note">Drawn in your browser from the numbers above. Nothing is uploaded.</p>
        </section>
    </div>
</div>

@push('scripts')
    <script src="{{ asset('js/share-card.js') }}?v={{ @filemtime(public_path('js/share-card.js')) }}"></script>
    <script>
        (function () {
            var data = @js($card);
            var canvas = document.getElementById('share-card');

            if (!canvas || !window.gamehubShareCard) {
                return;
            }

            window.gamehubShareCard.draw(canvas, data);

            document.getElementById('share-download').addEventListener('click', function () {
                window.gamehubShareCard.download(canvas, 'gamehub-card.png');
            });
        })();
    </script>
@endpush
