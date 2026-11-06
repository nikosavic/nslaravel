<x-app-layout :title="__('Dashboard')">
    <section class="section !pb-24">
        <div class="wrap">
            <h1 class="section-title">{{ __('Dashboard') }}</h1>
            <p class="section-desc">{{ Auth::user()->name }}</p>

            @php($isAdmin = Auth::user()->role === 'admin')

            <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @if ($isAdmin && Route::has('admin.projects.index'))
                    <a href="{{ route('admin.projects.index') }}" class="spec block transition hover:-translate-y-1">
                        <h2 class="spec-name">{{ __('ui.nav.admin_projects') }}</h2>
                        <p class="mt-2 text-[15px] text-ink/70">{{ __('ui.sections.projects.title') }}</p>
                    </a>
                @endif

                @if ($isAdmin && Route::has('games.entry'))
                    <a href="{{ route('games.entry') }}" class="gh-pill !h-auto flex-col !items-start gap-1 !px-6 !py-5" style="clip-path: polygon(16px 0, 100% 0, calc(100% - 16px) 100%, 0 100%)">
                        <span class="flex items-center gap-2.5 font-display text-xl font-extrabold">
                            @include('layouts.partials.gamehub-mark')
                            {{ __('ui.nav.gamehub') }}
                        </span>
                    </a>
                @endif

                <a href="{{ route('profile.edit') }}" class="spec block transition hover:-translate-y-1">
                    <h2 class="spec-name">{{ __('ui.nav.profile') }}</h2>
                    <p class="mt-2 text-[15px] text-ink/70">{{ Auth::user()->email }}</p>
                </a>
            </div>
        </div>
    </section>
</x-app-layout>
