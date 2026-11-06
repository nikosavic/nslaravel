<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="robots" content="noindex">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <script>
            (function () {
                try {
                    var stored = localStorage.getItem('theme');
                    var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                    document.documentElement.classList.toggle('dark', (stored || (prefersDark ? 'dark' : 'light')) === 'dark');
                } catch (e) {}
            })();
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div class="flex min-h-screen flex-col items-center justify-center px-5 py-10">
            <a href="{{ url('/') }}" class="logo-chip mb-8 h-16 w-16" aria-label="{{ config('app.name') }}">
                <x-application-logo class="h-11 w-11 object-contain" />
            </a>

            <div class="w-full max-w-md rounded-[1.75rem] border-2 border-ink bg-[#f7faf9] px-7 py-8 shadow-[8px_8px_0_0_rgb(var(--gold))] sm:px-9">
                <div class="text-gray-900">
                    {{ $slot }}
                </div>
            </div>

            <a href="{{ url('/') }}" class="mt-8 text-sm font-semibold text-ink/70 underline decoration-2 underline-offset-4 hover:text-ink">
                {{ config('app.name') }}
            </a>
        </div>
    </body>
</html>
