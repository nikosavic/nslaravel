<x-app-layout>
    <div class="max-w-xl mx-auto px-4 py-12 space-y-6">
        <h1 class="text-3xl font-bold">Contact</h1>

        @if(session('success'))
            <div class="p-3 rounded border bg-green-50 text-green-800">{{ session('success') }}</div>
        @endif

        <form method="POST" action="{{ route('contact.submit') }}" class="space-y-4">
            @csrf

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
            <input name="company" class="hidden" tabindex="-1" autocomplete="off">

            <div>
                <label class="text-sm">Message</label>
                <textarea name="message" rows="6" class="w-full rounded border p-2" required>{{ old('message') }}</textarea>
                @error('message') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
            </div>

            <button class="px-4 py-2 rounded bg-black text-white">Send</button>
        </form>
    </div>
</x-app-layout>
