<nav
    x-data="{
      open: false,
      theme: (localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')),
      applyTheme() {
        document.documentElement.classList.toggle('dark', this.theme === 'dark');
        localStorage.setItem('theme', this.theme);
      },
      toggleTheme() {
        this.theme = this.theme === 'dark' ? 'light' : 'dark';
        this.applyTheme();
      }
    }"
    x-init="applyTheme()"
    class="sticky top-0 z-50 py-1 border-b border-black/10 dark:border-white/10 bg-slate-200/70 dark:bg-neutral-950/60 backdrop-blur-xl"
>
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-20">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('home') . '#top' }}" class="flex items-center">
                        <x-application-logo class="block h-12 w-auto logo-glow" />
                    </a>
                </div>

                <!-- Navigation Links (Desktop) -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('home') . '#top'" :active="request()->routeIs('home')">
                        {{ __('ui.nav.home') }}
                    </x-nav-link>

                    <x-nav-link :href="route('home') . '#projects'">
                        {{ __('ui.nav.projects') }}
                    </x-nav-link>

                    <x-nav-link :href="route('home') . '#stack'">
                        {{ __('ui.nav.stack') }}
                    </x-nav-link>

                    <x-nav-link :href="route('home') . '#about'">
                        {{ __('ui.nav.about') }}
                    </x-nav-link>

                    <x-nav-link :href="route('home') . '#contact'">
                        {{ __('ui.nav.contact') }}
                    </x-nav-link>

                    @auth
                        @if(auth()->user()->role === 'admin' && Route::has('admin.projects.index'))
                            <x-nav-link :href="route('admin.projects.index')" :active="request()->routeIs('admin.projects.*')">
                                {{ __('ui.nav.admin_projects') }}
                            </x-nav-link>
                        @endif
                    @endauth
                </div>
            </div>

            <!-- Right side (Desktop) -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">

            {{-- Language toggle (URL-based: /en, /tr) --}}
@php
    $current = request()->route('locale') ?? app()->getLocale();
    $current = in_array($current, ['en','tr']) ? $current : 'en';

    $target = $current === 'tr' ? 'en' : 'tr';

    // Build "same page, other locale"
    $path = request()->path();          // e.g. "en/projects/foo"
    $parts = explode('/', $path);

    if (in_array($parts[0] ?? '', ['en','tr'])) {
        array_shift($parts);            // remove current locale
    }

    $newPath = $target . (count($parts) ? '/' . implode('/', $parts) : '');
    $newUrl  = url($newPath);
@endphp

<a href="{{ $newUrl }}"
   class="me-3 inline-flex items-center justify-center rounded-lg border border-black/10 dark:border-white/10
          bg-white/60 dark:bg-white/5 backdrop-blur px-3 py-2 text-sm
          text-gray-700 dark:text-white/70 hover:bg-white/80 dark:hover:bg-white/10">
    {{ strtoupper($target) }}
</a>

                <!-- Dark mode toggle -->
                <button type="button"
                        @click="toggleTheme()"
                        class="me-3 inline-flex items-center justify-center rounded-lg border border-black/10 dark:border-white/10
                               bg-white/60 dark:bg-white/5 backdrop-blur px-3 py-2 text-sm
                               text-gray-700 dark:text-white/70 hover:bg-white/80 dark:hover:bg-white/10">
                    <span x-text="theme === 'dark' ? '☾' : '☀︎'"></span>
                </button>

                <!-- Settings Dropdown -->
                @auth
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-600 dark:text-white/70
                                           bg-transparent hover:text-gray-900 dark:hover:text-white focus:outline-none transition ease-in-out duration-150">
                                <div>{{ Auth::user()->name }}</div>

                                <div class="ms-1">
                                    <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <x-dropdown-link :href="route('profile.edit')">
                                {{ __('ui.nav.profile') }}
                            </x-dropdown-link>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link :href="route('logout')"
                                                onclick="event.preventDefault(); this.closest('form').submit();">
                                    {{ __('ui.nav.logout') }}
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                @else
                    <div class="flex items-center gap-3">
                        <a href="{{ route('login') }}" class="text-sm underline text-gray-700 dark:text-white/70">
                            {{ __('ui.nav.login') }}
                        </a>
                        
                    </div>
                @endauth
            </div>

            <!-- Hamburger (Mobile) -->
            <div class="-me-2 flex items-center sm:hidden h-20">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md
                        text-gray-500 dark:text-white/70 hover:text-gray-700 dark:hover:text-white
                        hover:bg-black/5 dark:hover:bg-white/10 focus:outline-none transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu (Mobile) -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1 px-4">
            <x-responsive-nav-link :href="route('home') . '#top'">
                {{ __('ui.nav.home') }}
            </x-responsive-nav-link>

            <x-responsive-nav-link :href="route('home') . '#projects'">
                {{ __('ui.nav.projects') }}
            </x-responsive-nav-link>

            <x-responsive-nav-link :href="route('home') . '#stack'">
                {{ __('ui.nav.stack') }}
            </x-responsive-nav-link>

            <x-responsive-nav-link :href="route('home') . '#about'">
                {{ __('ui.nav.about') }}
            </x-responsive-nav-link>

            <x-responsive-nav-link :href="route('home') . '#contact'">
                {{ __('ui.nav.contact') }}
            </x-responsive-nav-link>

            @auth
                @if(auth()->user()->role === 'admin' && Route::has('admin.projects.index'))
                    <x-responsive-nav-link :href="route('admin.projects.index')">
                        {{ __('ui.nav.admin_projects') }}
                    </x-responsive-nav-link>
                @endif
            @endauth

            {{-- Mobile toggles row --}}
            <div class="pt-4 pb-4 border-t border-black/10 dark:border-white/10 flex items-center justify-between">
                @php($loc = app()->getLocale())
                <a href="{{ route('lang.switch', ['locale' => $loc === 'tr' ? 'en' : 'tr']) }}"
                   class="inline-flex items-center justify-center rounded-lg border border-black/10 dark:border-white/10
                          bg-white/60 dark:bg-white/5 backdrop-blur px-3 py-2 text-sm
                          text-gray-700 dark:text-white/70 hover:bg-white/80 dark:hover:bg-white/10">
                    {{ strtoupper($loc) }}
                </a>

                <button type="button"
                        @click="toggleTheme()"
                        class="inline-flex items-center justify-center rounded-lg border border-black/10 dark:border-white/10
                               bg-white/60 dark:bg-white/5 backdrop-blur px-3 py-2 text-sm
                               text-gray-700 dark:text-white/70 hover:bg-white/80 dark:hover:bg-white/10">
                    <span x-text="theme === 'dark' ? '☾' : '☀︎'"></span>
                </button>
            </div>
        </div>

        @auth
            <div class="pt-4 pb-1 border-t border-black/10 dark:border-white/10">
                <div class="px-4">
                    <div class="font-medium text-base text-gray-800 dark:text-white">{{ Auth::user()->name }}</div>
                    <div class="font-medium text-sm text-gray-500 dark:text-white/60">{{ Auth::user()->email }}</div>
                </div>

                <div class="mt-3 space-y-1 px-4 pb-4">
                    <x-responsive-nav-link :href="route('profile.edit')">
                        {{ __('ui.nav.profile') }}
                    </x-responsive-nav-link>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault(); this.closest('form').submit();">
                            {{ __('ui.nav.logout') }}
                        </x-responsive-nav-link>
                    </form>
                </div>
            </div>
        @else
            <div class="pt-4 pb-4 border-t border-black/10 dark:border-white/10 px-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <a href="{{ route('login') }}" class="text-sm underline text-gray-700 dark:text-white/70">
                        {{ __('ui.nav.login') }}
                    </a>
                    
                </div>
            </div>
        @endauth
    </div>
</nav>