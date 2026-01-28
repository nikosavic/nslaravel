<x-app-layout>
    <div class="max-w-xl mx-auto px-4 py-12 space-y-6">
        <h1 class="text-3xl font-bold">Contact</h1>

        @if(session('success'))
            <div class="p-3 rounded border bg-green-50 text-green-800">{{ session('success') }}</div>
        @endif

        <form method="POST" action="{{ route('contact.submit') }}" class="space-y-4">
            @csrf

            <input type="hidden" name="ts" value="{{ time() }}">

@if (config('services.turnstile.sitekey'))
    <input type="hidden" name="cf_token" id="cf_token" value="">
@endif


            <input type="hidden" name="ts" value="{{ time() }}">

@if (config('services.turnstile.sitekey'))
    <input type="hidden" name="cf_token" id="cf_token" value="">
@endif


            <div>
                <label class="text-sm">Name</label>
                <input name="name" value="{{ old('name') }}" class="w-full rounded border p-2" required>
                @error('name') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="text-sm">Email</label>
                <input name="email" type="email" value="{{ old('email') }}" class="w-full rounded border p-2" required>
                @error('email') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
            </div>

            {{-- honeypot --}}
            <div style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;" aria-hidden="true">
    <label>Leave this field empty</label>
    <input name="website" type="text" tabindex="-1" autocomplete="off" value="">
</div>


            <div>
                <label class="text-sm">Message</label>
                <textarea name="message" rows="6" class="w-full rounded border p-2" required>{{ old('message') }}</textarea>
                @error('message') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
            </div>

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
            document.getElementById('cf_token').value = token;
        }
    </script>
@endif



            <button class="px-4 py-2 rounded bg-black text-white">Send</button>
        </form>
    </div>
</x-app-layout>
