<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">

    <head>
    

        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300..900&family=Space+Grotesk:wght@300..700&display=swap" rel="stylesheet">


        <!-- Scripts -->
        <script>
  (function () {
    const stored = localStorage.getItem('theme');
    const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    const theme = stored ? stored : (prefersDark ? 'dark' : 'light');

    document.documentElement.classList.toggle('dark', theme === 'dark');

    window.__setTheme = function (next) {
      document.documentElement.classList.toggle('dark', next === 'dark');
      localStorage.setItem('theme', next);
    };
  })();
</script>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
    <div class="min-h-screen bg-slate-50 text-gray-900 dark:bg-neutral-950 dark:text-white">

            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
            <header class="bg-white shadow dark:bg-neutral-950/60 dark:text-white dark:border-b dark:border-white/10">

                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
