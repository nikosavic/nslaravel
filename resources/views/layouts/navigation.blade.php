@php
    // Current locale from the route (preferred) with a fallback.
    $loc = request()->route('locale') ?? app()->getLocale();
    $loc = in_array($loc, ['en', 'tr'], true) ? $loc : 'en';

    $homeUrl = route('public.home', ['locale' => $loc]);

    // "Same page, other language": swap the locale prefix, keep query string. The #hash is added on click (PHP never sees it).
    $target = $loc === 'tr' ? 'en' : 'tr';
    $parts = explode('/', request()->path());
    if (in_array($parts[0] ?? '', ['en', 'tr'], true)) {
        array_shift($parts);
    }
    $langBaseUrl = url($target . (count($parts) ? '/' . implode('/', $parts) : ''))
        . (request()->getQueryString() ? '?' . request()->getQueryString() : '');

    // Pages outside /en and /tr (login, admin, GameHub) switch language through the session.
    if (! in_array(request()->route('locale'), ['en', 'tr'], true)) {
        $langBaseUrl = route('lang.switch', ['locale' => $target]);
    }

    $links = [
        ['#top', 'ui.nav.home'],
        ['#projects', 'ui.nav.projects'],
        ['#stack', 'ui.nav.stack'],
        ['#about', 'ui.nav.about'],
        ['#contact', 'ui.nav.contact'],
    ];

    $isAdmin = auth()->check() && auth()->user()->role === 'admin';
    $hasGames = Route::has('games.entry');
    $inGames = request()->routeIs('games.*');
@endphp

<nav
    x-data="{
        open: false,
        theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light',
        toggleTheme() {
            this.theme = this.theme === 'dark' ? 'light' : 'dark';
            document.documentElement.classList.toggle('dark', this.theme === 'dark');
            try { localStorage.setItem('theme', this.theme); } catch (e) {}
        }
    }"
    @keydown.escape.window="open = false"
    class="nav-bar"
    aria-label="Main"
>
    <div class="wrap flex h-16 items-center justify-between gap-6">
        <a href="{{ $homeUrl }}#top" class="logo-chip flex-none" aria-label="{{ __('ui.nav.home') }}">
            <x-application-logo class="h-8 w-8 object-contain" />
        </a>

        {{-- Desktop links --}}
        <div class="hidden items-center gap-7 md:flex">
            @foreach ($links as [$anchor, $key])
                <a href="{{ $homeUrl }}{{ $anchor }}"
                   class="nav-link {{ $anchor === '#top' && request()->routeIs('public.home') ? 'is-active' : '' }}">{{ __($key) }}</a>
            @endforeach

            @if ($isAdmin && Route::has('admin.projects.index'))
                <a href="{{ route('admin.projects.index') }}"
                   class="nav-link {{ request()->routeIs('admin.projects.*') ? 'is-active' : '' }}">{{ __('ui.nav.admin_projects') }}</a>
            @endif
        </div>

        {{-- Desktop right side --}}
        <div class="hidden items-center gap-2.5 md:flex">
            @if ($hasGames)
                <a href="{{ route('games.entry') }}" class="gh-pill {{ $inGames ? 'is-active' : '' }}" title="{{ __('ui.nav.gamehub') }}">
                    @include('layouts.partials.gamehub-mark')
                    {{ __('ui.nav.gamehub') }}
                </a>
            @endif

            <a href="{{ $langBaseUrl }}" data-lang-base="{{ $langBaseUrl }}"
               onclick="this.href = this.dataset.langBase + (window.location.hash || '');"
               class="icon-btn" hreflang="{{ $target }}" aria-label="{{ strtoupper($target) }}">{{ strtoupper($target) }}</a>

            <button type="button" @click="toggleTheme()" class="icon-btn" aria-label="Toggle dark mode">
                @include('layouts.partials.theme-icon')
            </button>

            @auth
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center gap-1 rounded-full px-3 py-2 text-sm font-semibold text-ink/80 transition hover:text-ink focus:outline-none">
                            {{ Auth::user()->name }}
                            <svg class="h-4 w-4 fill-current" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">{{ __('ui.nav.profile') }}</x-dropdown-link>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">
                                {{ __('ui.nav.logout') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            @else
                <a href="{{ route('login') }}" class="px-2 text-sm font-semibold text-ink/70 underline decoration-2 underline-offset-4 hover:text-ink">{{ __('ui.nav.login') }}</a>
            @endauth
        </div>

        {{-- Mobile: GameHub stays reachable without opening the menu --}}
        <div class="flex items-center gap-2 md:hidden">
            @if ($hasGames)
                <a href="{{ route('games.entry') }}" class="gh-pill !h-9 !px-4 text-sm" title="{{ __('ui.nav.gamehub') }}">
                    @include('layouts.partials.gamehub-mark')
                    {{ __('ui.nav.gamehub') }}
                </a>
            @endif

            <button type="button" @click="open = ! open" class="icon-btn !px-0 w-10" :aria-expanded="open" aria-label="Menu">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true">
                    <path x-show="! open" d="M4 7h16M4 12h16M4 17h16"/>
                    <path x-show="open" x-cloak d="M6 6l12 12M18 6 6 18"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- Mobile panel --}}
    <div x-show="open" x-cloak x-transition.opacity.duration.150ms class="border-t border-ink/10 bg-paper md:hidden">
        <div class="wrap py-5">
            <div class="flex flex-col">
                @foreach ($links as [$anchor, $key])
                    <a href="{{ $homeUrl }}{{ $anchor }}" @click="open = false"
                       class="border-b border-ink/10 py-3.5 font-display text-2xl font-bold tracking-tight">{{ __($key) }}</a>
                @endforeach

                @if ($isAdmin && Route::has('admin.projects.index'))
                    <a href="{{ route('admin.projects.index') }}" class="border-b border-ink/10 py-3.5 font-display text-2xl font-bold tracking-tight">{{ __('ui.nav.admin_projects') }}</a>
                @endif
            </div>

            <div class="mt-5 flex items-center justify-between gap-3">
                <a href="{{ $langBaseUrl }}" data-lang-base="{{ $langBaseUrl }}"
                   onclick="this.href = this.dataset.langBase + (window.location.hash || '');"
                   class="icon-btn" hreflang="{{ $target }}">{{ strtoupper($target) }}</a>

                <button type="button" @click="toggleTheme()" class="icon-btn" aria-label="Toggle dark mode">
                    @include('layouts.partials.theme-icon')
                </button>
            </div>

            <div class="mt-5 border-t border-ink/10 pt-5">
                @auth
                    <div class="text-base font-semibold">{{ Auth::user()->name }}</div>
                    <div class="text-sm text-ink/60">{{ Auth::user()->email }}</div>

                    <div class="mt-3 flex flex-col gap-1 text-[15px] font-medium">
                        <a href="{{ route('profile.edit') }}" class="py-2">{{ __('ui.nav.profile') }}</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="py-2 text-left">{{ __('ui.nav.logout') }}</button>
                        </form>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="text-[15px] font-semibold underline decoration-2 underline-offset-4">{{ __('ui.nav.login') }}</a>
                @endauth
            </div>
        </div>
    </div>
</nav>
