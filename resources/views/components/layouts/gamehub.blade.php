<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="dark">
    <title>{{ isset($title) ? $title.' · ' : '' }}GameHub</title>

    <link rel="preload" href="{{ asset('fonts/big-shoulders-display-latin-900-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/schibsted-grotesk-latin-400-normal.woff2') }}" as="font" type="font/woff2" crossorigin>

    @php($cssFile = public_path('css/gamehub.css'))
    <link rel="stylesheet" href="{{ asset('css/gamehub.css') }}?v={{ is_file($cssFile) ? filemtime($cssFile) : 0 }}">

    @stack('head')

    @livewireStyles
</head>
<body>
    <header class="topbar">
        <div class="shell topbar-in">
            <a class="brand" href="{{ route('games.entry') }}">
                <svg class="glyph" viewBox="0 0 30 26" aria-hidden="true">
                    <g transform="skewX(-14)" transform-origin="0 26">
                        <rect x="6" y="12" width="5" height="14" fill="#ff5a52"/>
                        <rect x="13" y="6" width="5" height="20" fill="#ffc83d"/>
                        <rect x="20" y="0" width="5" height="26" fill="#35f0b0"/>
                        <rect x="27" y="9" width="5" height="17" fill="#8aa2ff"/>
                    </g>
                </svg>
                GameHub
            </a>

            @if (auth()->check() && auth()->user()->role === 'admin')
                <nav class="nav" aria-label="Main">
                    <a href="{{ route('games.library') }}" @class(['is-active' => request()->routeIs('games.library')]) @if (request()->routeIs('games.library')) aria-current="page" @endif>Library</a>
                    <a href="{{ route('games.play-next') }}" @class(['is-active' => request()->routeIs('games.play-next')]) @if (request()->routeIs('games.play-next')) aria-current="page" @endif>Play next</a>
                    <a href="{{ route('games.stats') }}" @class(['is-active' => request()->routeIs('games.stats')]) @if (request()->routeIs('games.stats')) aria-current="page" @endif>Stats</a>
                    <a href="{{ route('games.accounts') }}" @class(['is-active' => request()->routeIs('games.accounts')]) @if (request()->routeIs('games.accounts')) aria-current="page" @endif>Accounts</a>
                </nav>
            @endif

            <div class="topbar-user">
                <a class="sitelink" href="{{ url('/') }}" title="Back to the portfolio">
                    <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M10 3 5 8l5 5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <span>nikolasavic.dev</span>
                </a>

                @if (auth()->check() && auth()->user()->role === 'admin')
                    <a class="who" href="{{ route('games.profile') }}" title="Profile and sharing"><span class="who-full">{{ auth()->user()->email }}</span><span class="who-short">Profile</span></a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-quiet btn-sm">Log out</button>
                    </form>
                @endif
            </div>
        </div>
    </header>

    <main class="shell page">
        {{ $slot }}
    </main>

    @livewireScripts
    @stack('scripts')
</body>
</html>
