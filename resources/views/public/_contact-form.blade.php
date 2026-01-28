@if(session('success'))
    <div class="p-3 mb-4 rounded border bg-green-50 text-green-800">
        {{ session('success') }}
    </div>
@endif

@if ($errors->any())
    <div class="p-3 mb-4 rounded border bg-red-50 text-red-800">
        <div class="font-semibold mb-1">There was a problem:</div>
        <ul class="list-disc list-inside space-y-1">
            @foreach ($errors->all() as $error)
                <li class="text-sm">{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('contact.submit') }}" class="space-y-4">
    @csrf

    {{-- Timing anti-bot --}}
    <input type="hidden" name="ts" value="{{ time() }}">

    {{-- Turnstile token (populated by callback) --}}
    @if (config('services.turnstile.sitekey'))
        <input type="hidden" name="cf_token" id="cf_token" value="">
    @endif

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="text-sm">Name</label>
            <input name="name"
                   value="{{ old('name') }}"
                   class="w-full rounded border p-2 bg-white/80 dark:bg-black/20"
                   required>
            @error('name') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
        </div>

        <div>
            <label class="text-sm">Email</label>
            <input name="email"
                   type="email"
                   value="{{ old('email') }}"
                   class="w-full rounded border p-2 bg-white/80 dark:bg-black/20"
                   required>
            @error('email') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
        </div>
    </div>

    {{-- Honeypot (bots fill this; humans won't). Avoid "company" to reduce autofill --}}
    <div style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;" aria-hidden="true">
        <label>Leave this field empty</label>
        <input name="website" type="text" tabindex="-1" autocomplete="off" value="">
    </div>

    <div>
        <label class="text-sm">Message</label>
        <textarea name="message"
                  rows="6"
                  class="w-full rounded border p-2 bg-white/80 dark:bg-black/20"
                  required>{{ old('message') }}</textarea>
        @error('message') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
    </div>

    {{-- Cloudflare Turnstile --}}
    @if (config('services.turnstile.sitekey'))
        <div class="pt-2">
            <div
                class="cf-turnstile"
                data-sitekey="{{ config('services.turnstile.sitekey') }}"
                data-callback="onTurnstileSuccess"
            ></div>
        </div>

        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
        <script>
            function onTurnstileSuccess(token) {
                var el = document.getElementById('cf_token');
                if (el) el.value = token;
            }
        </script>
    @endif

    <button class="px-4 py-2 rounded bg-black text-white dark:bg-white dark:text-black">
        Send
    </button>
</form>