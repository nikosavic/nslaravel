@if (session('success'))
    <div class="alert-ok" role="status">{{ session('success') }}</div>
@endif

@if ($errors->any())
    <div class="alert-bad" role="alert">
        <div class="mb-1 font-semibold">{{ __('ui.form.problem') }}</div>
        <ul class="list-inside list-disc space-y-1">
            @foreach ($errors->all() as $error)
                <li class="text-sm">{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('public.contact.submit', ['locale' => request()->route('locale') ?? app()->getLocale()]) }}" class="space-y-7">
    @csrf

    {{-- Timing anti-bot --}}
    <input type="hidden" name="ts" value="{{ time() }}">

    {{-- Turnstile token (populated by callback) --}}
    @if (config('services.turnstile.sitekey'))
        <input type="hidden" name="cf_token" id="cf_token" value="">
    @endif

    <div class="grid gap-7 sm:grid-cols-2">
        <div>
            <label for="contact-name" class="field-label">{{ __('ui.form.name') }}</label>
            <input id="contact-name" name="name" value="{{ old('name') }}" class="field" autocomplete="name" required>
        </div>

        <div>
            <label for="contact-email" class="field-label">{{ __('ui.form.email') }}</label>
            <input id="contact-email" name="email" type="email" value="{{ old('email') }}" class="field" autocomplete="email" required>
        </div>
    </div>

    {{-- Honeypot (bots fill this; humans won't). Avoid "company" to reduce autofill --}}
    <div style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;" aria-hidden="true">
        <label>Leave this field empty</label>
        <input name="website" type="text" tabindex="-1" autocomplete="off" value="">
    </div>

    <div>
        <label for="contact-message" class="field-label">{{ __('ui.form.message') }}</label>
        <textarea id="contact-message" name="message" rows="5" class="field resize-y" required>{{ old('message') }}</textarea>
    </div>

    {{-- Cloudflare Turnstile --}}
    @if (config('services.turnstile.sitekey'))
        <div>
            <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.sitekey') }}" data-theme="dark" data-callback="onTurnstileSuccess"></div>
        </div>

        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
        <script>
            function onTurnstileSuccess(token) {
                var el = document.getElementById('cf_token');
                if (el) el.value = token;
            }
        </script>
    @endif

    <button type="submit" class="btn btn-gold">{{ __('ui.form.send') }}</button>
</form>
