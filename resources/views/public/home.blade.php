@php
    $locale = request()->route('locale') ?? app()->getLocale();
    $isAdmin = auth()->check() && auth()->user()->role === 'admin';
    $projectList = ($featured->isEmpty() && $heroProject) ? collect([$heroProject]) : $featured;
@endphp

<x-app-layout>
    <div id="top"></div>

    {{-- ============================== HERO ============================== --}}
    <section class="hero-dots relative overflow-hidden">
        <div class="wrap grid gap-14 pb-20 pt-14 sm:pt-20 lg:grid-cols-12 lg:gap-10 lg:pb-28 lg:pt-24">
            <div class="lg:col-span-7">
                <p class="status-line">
                    <span class="status-dot" aria-hidden="true"></span>
                    {{ __('ui.hero.badge') }}
                </p>

                <h1 class="mt-7">
                    <span class="rise-mask block"><span class="h1-main rise-in" style="--d: 0ms">{{ __('ui.hero.title') }}</span></span>
                    <span class="rise-mask block"><span class="h1-sub rise-in" style="--d: 140ms">{{ __('ui.hero.subtitle') }}</span></span>
                </h1>

                <p class="mt-8 max-w-xl text-lg leading-relaxed text-ink/75">
                    {{ __('ui.hero.body') }}
                </p>

                <div class="mt-9 flex flex-wrap gap-3">
                    <a href="#projects" class="btn btn-solid">{{ __('ui.hero.cta_projects') }}</a>
                    <a href="#contact" class="btn btn-line">{{ __('ui.hero.cta_contact') }}</a>
                </div>

                <ul class="mt-9 flex flex-wrap gap-2" aria-label="Skills">
                    @foreach (['auth_roles', 'admin_crud', 'sqlite_mysql', 'queues', 'emails', 'deploy_ready'] as $chip)
                        <li class="tag">{{ __('ui.hero.chips.'.$chip) }}</li>
                    @endforeach
                </ul>
            </div>

            <div class="lg:col-span-5 lg:pt-6">
                @if ($heroProject)
                    <a href="{{ route('public.projects.show', ['locale' => $locale, 'project' => $heroProject->slug]) }}"
                       class="ticket ticket-in ticket-tilt block lg:flex">
                        <span class="ticket-body">
                            <span>
                                <span class="text-sm font-semibold opacity-75">{{ __('ui.hero.featured_label') }}</span>
                                <span class="ticket-title mt-3 block">{{ $heroProject->title }}</span>

                                @if ($heroProject->summary)
                                    <span class="mt-4 block max-w-sm text-[15px] leading-snug opacity-85">{{ $heroProject->summary }}</span>
                                @endif
                            </span>

                            <span class="inline-flex items-center gap-2 text-[15px] font-bold underline decoration-2 underline-offset-4">
                                {{ __('ui.hero.featured_cta') }}
                                <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 8h10M9 4l4 4-4 4"/></svg>
                            </span>
                        </span>

                        <span class="ticket-stub hidden lg:grid" aria-hidden="true"><span>{{ __('ui.hero.featured_label') }}</span></span>
                    </a>
                @elseif ($isAdmin)
                    {{-- Only the owner sees this hint; visitors just get the headline. --}}
                    <div class="rounded-2xl border-2 border-dashed border-ink/40 p-7">
                        <div class="font-display text-xl font-bold">{{ __('ui.hero.no_featured_title') }}</div>
                        <div class="mt-2 text-[15px] text-ink/70">{!! __('ui.hero.no_featured_body') !!}</div>
                    </div>
                @endif
            </div>
        </div>

        {{-- What I build --}}
        <div class="wrap pb-6">
            <h2 class="text-base font-semibold text-ink/60">{{ __('ui.hero.what_i_build') }}</h2>

            <div class="mt-5 grid gap-x-10 gap-y-8 sm:grid-cols-3">
                @foreach (['backend', 'ui', 'integrations'] as $card)
                    <div class="spec">
                        <h3 class="spec-name">{{ __('ui.hero.cards.'.$card.'_title') }}</h3>
                        <p class="mt-2 text-[15px] leading-relaxed text-ink/70">{{ __('ui.hero.cards.'.$card.'_body') }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============================= PROJECTS ============================= --}}
    <section id="projects" class="section on-gold">
        <div class="wrap section-grid">
            <div class="section-head">
                <div>
                    <h2 class="section-title">{{ __('ui.sections.projects.title') }}</h2>
                    <p class="section-desc">{{ __('ui.sections.projects.desc') }}</p>
                </div>
            </div>

            <div class="section-body">
                @if ($projectList->isEmpty())
                    <div class="rounded-2xl border-2 border-dashed border-ink/40 p-7 text-ink/70">
                        {!! __('ui.sections.projects.empty') !!}
                    </div>
                @else
                    <div>
                        @foreach ($projectList as $p)
                            <a href="{{ route('public.projects.show', ['locale' => $locale, 'project' => $p->slug]) }}"
                               class="project-row {{ $p->imageUrl() ? 'has-img' : '' }}">
                                @if ($p->imageUrl())
                                    <img src="{{ $p->imageUrl() }}" alt="{{ $p->title }}" class="project-thumb" loading="lazy">
                                @endif

                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                        <span class="project-name">{{ $p->title }}</span>
                                        @if ($p->featured)
                                            <span class="pill pill-gold">{{ __('ui.projects.badge_featured') }}</span>
                                        @endif
                                    </div>

                                    @if ($p->summary)
                                        <p class="mt-1.5 line-clamp-2 max-w-xl text-[15px] leading-snug text-ink/70">{{ $p->summary }}</p>
                                    @endif
                                </div>

                                <span class="pill hidden sm:inline-flex">{{ $p->status }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif

                <div class="mt-8">
                    <a href="{{ route('public.projects.index', ['locale' => $locale]) }}"
                       class="inline-flex items-center gap-2 font-semibold underline decoration-2 underline-offset-4 decoration-ink/40 hover:decoration-ink">
                        {{ __('ui.sections.projects.view_all') }}
                        <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 8h10M9 4l4 4-4 4"/></svg>
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- ============================ RECENT RUNS ============================ --}}
    @if ($runlog)
        @php
            $rl = $runlog;
            $rlStats = $rl['stats'];
            $rlRuns = array_slice($rl['runs'], 0, 5);
            $rlUrl = \App\Services\RunLogFeed::profileUrl($locale);
        @endphp
        <section id="runs" class="section art art-topo">
            <div class="wrap section-grid">
                <div class="section-head">
                    <div>
                        <h2 class="section-title">{{ __('ui.sections.runlog.title') }}</h2>
                        <p class="section-desc">{{ __('ui.sections.runlog.desc') }}</p>
                    </div>
                </div>

                <div class="section-body">
                    <p class="text-[15px] text-ink/70">
                        <strong class="font-display text-2xl text-ink">{{ number_format($rlStats['distance_km'], 1) }} km</strong>
                        {{ __('ui.sections.runlog.year', ['year' => $rl['year']]) }}
                        · {{ $rlStats['runs'] }} {{ __('ui.sections.runlog.runs') }}
                        @if ($rlStats['avg_pace'])
                            · {{ $rlStats['avg_pace'] }}/km {{ __('ui.sections.runlog.avg') }}
                        @endif
                        · {{ number_format($rlStats['longest_km'], 1) }} km {{ __('ui.sections.runlog.longest') }}
                    </p>

                    <div class="mt-5">
                        @foreach ($rlRuns as $run)
                            <div class="project-row" style="grid-template-columns: 1fr auto;">
                                <div>
                                    <span class="project-name">{{ number_format($run['distance_km'], 2) }} km</span>
                                    <span class="ml-3 text-[15px] text-ink/70">{{ $run['duration'] }} · {{ $run['pace'] }}/km</span>
                                </div>
                                <span class="pill">{{ \Carbon\Carbon::parse($run['date'])->locale($locale)->translatedFormat('j M') }}</span>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-8">
                        <a href="{{ $rlUrl }}" target="_blank" rel="noopener"
                           class="inline-flex items-center gap-2 font-semibold underline decoration-2 underline-offset-4 decoration-gold hover:decoration-ink">
                            {{ __('ui.sections.runlog.open') }}
                            <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 8h10M9 4l4 4-4 4"/></svg>
                        </a>
                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- ============================== STACK ============================== --}}
    <section id="stack" class="section bg-panel art art-layers">
        <div class="wrap section-grid">
            <div class="section-head">
                <div>
                    <h2 class="section-title">{{ __('ui.sections.stack.title') }}</h2>
                    <p class="section-desc">{{ __('ui.sections.stack.desc') }}</p>
                </div>
            </div>

            <div class="section-body">
                <div class="grid gap-x-10 gap-y-9 sm:grid-cols-2">
                    @foreach (['laravel', 'blade_tailwind', 'alpine_livewire', 'sqlite_mysql', 'apis_webhooks', 'deployment'] as $item)
                        <div class="spec">
                            <h3 class="spec-name">{{ __('ui.stack_items.'.$item.'.title') }}</h3>
                            <p class="mt-2 text-[15px] leading-relaxed text-ink/70">{{ __('ui.stack_items.'.$item.'.desc') }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- ============================== ABOUT ============================== --}}
    <section id="about" class="section">
        <div class="wrap section-grid">
            <div class="section-head">
                <div>
                    <h2 class="section-title">{{ __('ui.sections.about.title') }}</h2>
                    <p class="section-desc">{{ __('ui.sections.about.desc') }}</p>

                    <figure class="portrait mt-10">
                        <img src="{{ asset('images/me.jpeg') }}" alt="{{ __('ui.about.photo_alt') }}" width="461" height="461" loading="lazy">
                    </figure>

                    <p class="mt-9 font-display text-lg font-bold tracking-tight">
                        {{ __('ui.about.name') }}
                        <span class="block text-[15px] font-medium text-ink/65">{{ __('ui.about.role') }}</span>
                    </p>
                </div>
            </div>

            <div class="section-body">
                <p class="max-w-2xl font-display text-2xl font-semibold leading-snug tracking-tight sm:text-3xl">{{ __('ui.about.p1') }}</p>
                <p class="mt-6 max-w-2xl text-lg leading-relaxed text-ink/75">{{ __('ui.about.p2') }}</p>

                <div class="mt-12 grid gap-x-10 gap-y-10 sm:grid-cols-2">
                    <div class="spec">
                        <h3 class="spec-name">{{ __('ui.about.strengths.title') }}</h3>
                        <ul class="check-list mt-3">
                            @foreach (['i1', 'i2', 'i3', 'i4'] as $i)
                                <li>
                                    <svg viewBox="0 0 16 16" fill="none" stroke="rgb(var(--gold))" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 8.5 3.2 3.2L13 4.5"/></svg>
                                    {{ __('ui.about.strengths.'.$i) }}
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="spec">
                        <h3 class="spec-name">{{ __('ui.about.learning.title') }}</h3>
                        <ul class="check-list mt-3">
                            @foreach (['i1', 'i2', 'i3', 'i4'] as $i)
                                <li>
                                    <svg viewBox="0 0 16 16" fill="none" stroke="rgb(var(--lilac))" stroke-width="3" stroke-linecap="round" aria-hidden="true"><circle cx="8" cy="8" r="4.5"/></svg>
                                    {{ __('ui.about.learning.'.$i) }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                <div class="mt-12 flex flex-wrap gap-3">
                    <a href="#projects" class="btn btn-solid">{{ __('ui.about.cta_projects') }}</a>
                    <a href="#contact" class="btn btn-line">{{ __('ui.about.cta_contact') }}</a>
                </div>
            </div>
        </div>
    </section>

    {{-- ============================= CONTACT ============================= --}}
    <section id="contact" class="section !pb-0">
        <div class="wrap">
            <div class="contact-panel px-6 py-12 sm:px-12 sm:py-16">
                <div class="pointer-events-none absolute -right-10 -top-10 h-24 w-24 rounded-full bg-gold/90 sm:-right-16 sm:-top-16 sm:h-56 sm:w-56" aria-hidden="true"></div>
                <div class="pointer-events-none absolute -bottom-10 right-24 hidden h-24 w-24 rounded-full border-[10px] border-lilac sm:block" aria-hidden="true"></div>

                <div class="relative grid gap-10 lg:grid-cols-12">
                    <div class="lg:col-span-5">
                        <h2 class="section-title text-on-deep">{{ __('ui.sections.contact.title') }}</h2>
                        <p class="mt-4 max-w-sm text-lg leading-relaxed text-on-deep/80">{{ __('ui.sections.contact.desc') }}</p>
                    </div>

                    <div class="lg:col-span-7">
                        @include('public._contact-form')
                    </div>
                </div>
            </div>
        </div>

        @include('layouts.footer')
    </section>
</x-app-layout>
