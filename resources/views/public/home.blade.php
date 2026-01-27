<x-app-layout>
    <div id="top"></div>

    {{-- Page canvas --}}
    <div class="bg-white text-gray-900 dark:bg-neutral-950 dark:text-white">

        {{-- HERO BAND --}}
        <section class="relative overflow-hidden
            bg-gradient-to-br from-slate-50 via-white to-indigo-50
            dark:from-neutral-950 dark:via-neutral-900 dark:to-indigo-950">

            {{-- hero background --}}
            <div class="pointer-events-none absolute inset-0
                bg-[linear-gradient(120deg,rgba(99,102,241,.08),transparent_40%,rgba(16,185,129,.08))]
                dark:bg-[linear-gradient(120deg,rgba(99,102,241,.18),transparent_40%,rgba(16,185,129,.12))]">
            </div>

            <div class="relative max-w-6xl mx-auto px-4 pt-20 pb-20 sm:pt-28 sm:pb-28">
                <div class="grid lg:grid-cols-12 gap-10 items-start">
                    <div class="lg:col-span-7 space-y-7">
                        <div class="inline-flex items-center gap-2 rounded-full border border-black/10 dark:border-white/10 px-3 py-1 text-xs bg-white/60 dark:bg-white/5 backdrop-blur">
                            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                            {{ __('ui.hero.badge') }}
                        </div>

                        <h1 class="text-4xl sm:text-6xl font-bold tracking-tight leading-tight font-display">
                            <span class="bg-gradient-to-r from-fuchsia-500 via-indigo-500 to-cyan-400 bg-clip-text text-transparent">
                                {{ __('ui.hero.title') }}
                            </span>
                            <span class="block text-gray-600 dark:text-white/70">
                                {{ __('ui.hero.subtitle') }}
                            </span>
                        </h1>

                        <p class="text-base sm:text-lg text-gray-700 dark:text-white/70 max-w-2xl">
                            {{ __('ui.hero.body') }}
                        </p>

                        <div class="flex flex-wrap gap-3">
                            <a href="#projects"
                               class="px-5 py-2.5 rounded-lg bg-black text-white dark:bg-white dark:text-black">
                                {{ __('ui.hero.cta_projects') }}
                            </a>
                            <a href="#contact"
                               class="px-5 py-2.5 rounded-lg border border-black/15 dark:border-white/15 bg-white/40 dark:bg-white/0 backdrop-blur">
                                {{ __('ui.hero.cta_contact') }}
                            </a>
                        </div>

                        <div class="flex flex-wrap gap-2 pt-2 text-sm">
                            @foreach([
                                'ui.hero.chips.auth_roles',
                                'ui.hero.chips.admin_crud',
                                'ui.hero.chips.sqlite_mysql',
                                'ui.hero.chips.queues',
                                'ui.hero.chips.emails',
                                'ui.hero.chips.deploy_ready',
                            ] as $chipKey)
                                <span class="rounded-full border border-black/10 dark:border-white/10 px-3 py-1 text-gray-700 dark:text-white/70 bg-white/50 dark:bg-white/0 backdrop-blur">
                                    {{ __($chipKey) }}
                                </span>
                            @endforeach
                        </div>
                    </div>

                    <div class="lg:col-span-5 space-y-4">
                        @if($heroProject)
                            <a href="{{ route('projects.show', $heroProject->slug) }}"
                               class="block rounded-3xl border border-black/10 dark:border-white/10
                                      bg-white/70 dark:bg-white/5 backdrop-blur p-7 sm:p-8
                                      hover:shadow-lg transition">
                                <div class="text-xs uppercase tracking-wider text-gray-500 dark:text-white/40">
                                    {{ __('ui.hero.featured_label') }}
                                </div>
                                <div class="mt-2 text-2xl font-bold">{{ $heroProject->title }}</div>

                                @if($heroProject->summary)
                                    <div class="mt-3 text-sm text-gray-700 dark:text-white/70">
                                        {{ $heroProject->summary }}
                                    </div>
                                @endif

                                <div class="mt-5 inline-flex items-center gap-2 text-sm underline text-gray-800 dark:text-white/80">
                                    {{ __('ui.hero.featured_cta') }} <span aria-hidden="true">→</span>
                                </div>
                            </a>
                        @else
                            <div class="rounded-3xl border border-black/10 dark:border-white/10 bg-white/70 dark:bg-white/5 backdrop-blur p-7">
                                <div class="font-semibold">{{ __('ui.hero.no_featured_title') }}</div>
                                <div class="mt-2 text-sm text-gray-600 dark:text-white/60">
                                    {!! __('ui.hero.no_featured_body') !!}
                                </div>
                            </div>
                        @endif

                        <div class="rounded-3xl border border-black/10 dark:border-white/10 bg-white/70 dark:bg-white/5 backdrop-blur p-7 space-y-4">
                            <div class="text-sm font-semibold text-gray-700 dark:text-white/80">
                                {{ __('ui.hero.what_i_build') }}
                            </div>

                            <div class="grid gap-3">
                                <div class="rounded-2xl border border-black/10 dark:border-white/10 p-4">
                                    <div class="font-semibold">{{ __('ui.hero.cards.backend_title') }}</div>
                                    <div class="text-sm text-gray-600 dark:text-white/60">{{ __('ui.hero.cards.backend_body') }}</div>
                                </div>
                                <div class="rounded-2xl border border-black/10 dark:border-white/10 p-4">
                                    <div class="font-semibold">{{ __('ui.hero.cards.ui_title') }}</div>
                                    <div class="text-sm text-gray-600 dark:text-white/60">{{ __('ui.hero.cards.ui_body') }}</div>
                                </div>
                                <div class="rounded-2xl border border-black/10 dark:border-white/10 p-4">
                                    <div class="font-semibold">{{ __('ui.hero.cards.integrations_title') }}</div>
                                    <div class="text-sm text-gray-600 dark:text-white/60">{{ __('ui.hero.cards.integrations_body') }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>


        {{-- PROJECTS BAND --}}
        <section id="projects" class="relative py-20 sm:py-24 scroll-mt-24 overflow-hidden
            bg-gradient-to-br from-indigo-50 via-white to-cyan-50
            dark:from-neutral-950 dark:via-neutral-900 dark:to-indigo-950">

            {{-- Noise (unique ID!) --}}
            <svg class="pointer-events-none absolute inset-0 w-full h-full opacity-[0.05] dark:opacity-[0.07]" aria-hidden="true">
                <filter id="noiseFilterProjects">
                    <feTurbulence type="fractalNoise" baseFrequency="0.8" numOctaves="4" stitchTiles="stitch" />
                </filter>
                <rect width="100%" height="100%" filter="url(#noiseFilterProjects)" opacity="0.18"></rect>
            </svg>

            <div class="pointer-events-none absolute inset-0">
                <div class="absolute -top-28 left-10 h-72 w-72 rounded-full bg-cyan-400/12 blur-3xl"></div>
                <div class="absolute bottom-0 right-0 h-96 w-96 rounded-full bg-fuchsia-500/10 blur-3xl"></div>
                <div class="absolute inset-0 border-y border-black/10 dark:border-white/10"></div>
                <div class="absolute inset-0 bg-white/40 dark:bg-white/0"></div>
            </div>

            <div class="relative max-w-6xl mx-auto px-4">
                <div x-reveal class="transition-all duration-700 ease-out rounded-3xl border border-black/10 dark:border-white/10 bg-white/70 dark:bg-white/5 backdrop-blur p-8 sm:p-12 space-y-10">
                    <div class="relative pt-6">
                        <div class="absolute -top-2 left-0 text-6xl sm:text-7xl font-display font-bold
                                    text-black/10 dark:text-white/10 select-none leading-none">
                            01
                        </div>

                        <h2 class="relative text-3xl font-bold tracking-widest uppercase heading-tracking">
                            {{ __('ui.sections.projects.title') }}
                        </h2>

                        <p class="mt-2 text-gray-600 dark:text-white/60 max-w-2xl">
                            {{ __('ui.sections.projects.desc') }}
                        </p>
                    </div>

                    @if($featured->isEmpty())
                        <div class="rounded-xl border border-black/10 dark:border-white/10 p-6 text-gray-600 dark:text-white/60">
                            {!! __('ui.sections.projects.empty') !!}
                        </div>
                    @else
                        <div x-reveal class="transition-all duration-700 ease-out grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach($featured as $p)
                                <a href="{{ route('projects.show', $p->slug) }}"
                                   class="group rounded-2xl border border-black/10 dark:border-white/10 bg-white/60 dark:bg-white/5 backdrop-blur p-5 hover:shadow transition">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="font-semibold group-hover:underline">{{ $p->title }}</div>
                                        @if($p->featured)
                                            <span class="text-[11px] rounded-full px-2 py-1 border border-black/10 dark:border-white/10">
                                                {{ __('ui.projects.badge_featured') }}
                                            </span>
                                        @endif
                                    </div>

                                    @if($p->summary)
                                        <div class="mt-2 text-sm text-gray-600 dark:text-white/60 line-clamp-3">
                                            {{ $p->summary }}
                                        </div>
                                    @endif

                                    <div class="mt-4 text-xs text-gray-500 dark:text-white/40">
                                        {{ strtoupper($p->status) }}
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @endif

                    <div>
                        <a href="{{ route('projects.index') }}" class="underline text-sm text-gray-700 dark:text-white/70">
                            {{ __('ui.sections.projects.view_all') }} →
                        </a>
                    </div>
                </div>
            </div>
        </section>


        {{-- STACK BAND --}}
        <section id="stack" class="relative py-20 sm:py-24 scroll-mt-24 overflow-hidden
            bg-gradient-to-br from-emerald-50 via-white to-sky-50
            dark:from-neutral-950 dark:via-neutral-900 dark:to-emerald-950">

            {{-- Noise (unique ID!) --}}
            <svg class="pointer-events-none absolute inset-0 w-full h-full opacity-[0.05] dark:opacity-[0.07]" aria-hidden="true">
                <filter id="noiseFilterStack">
                    <feTurbulence type="fractalNoise" baseFrequency="0.8" numOctaves="4" stitchTiles="stitch" />
                </filter>
                <rect width="100%" height="100%" filter="url(#noiseFilterStack)" opacity="0.18"></rect>
            </svg>

            <div class="pointer-events-none absolute inset-0">
                <div class="absolute -top-24 -right-16 h-80 w-80 rounded-full bg-indigo-500/12 blur-3xl"></div>
                <div class="absolute bottom-0 left-0 h-96 w-96 rounded-full bg-emerald-500/8 blur-3xl"></div>
                <div class="absolute inset-0 border-y border-black/10 dark:border-white/10"></div>
            </div>

            <div class="relative max-w-6xl mx-auto px-4">
                <div x-reveal class="transition-all duration-700 ease-out rounded-3xl border border-black/10 dark:border-white/10 bg-white/70 dark:bg-white/5 backdrop-blur p-8 sm:p-12 space-y-10">
                    <div class="relative pt-6">
                        <div class="absolute -top-2 left-0 text-6xl sm:text-7xl font-display font-bold
                                    text-black/10 dark:text-white/10 select-none leading-none">
                            02
                        </div>

                        <h2 class="relative text-3xl font-bold tracking-widest uppercase heading-tracking">
                            {{ __('ui.sections.stack.title') }}
                        </h2>

                        <p class="mt-2 text-gray-600 dark:text-white/60 max-w-2xl">
                            {{ __('ui.sections.stack.desc') }}
                        </p>
                    </div>

                    <div x-reveal class="transition-all duration-700 ease-out grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach([
                            ['ui.stack_items.laravel.title','ui.stack_items.laravel.desc'],
                            ['ui.stack_items.blade_tailwind.title','ui.stack_items.blade_tailwind.desc'],
                            ['ui.stack_items.alpine_livewire.title','ui.stack_items.alpine_livewire.desc'],
                            ['ui.stack_items.sqlite_mysql.title','ui.stack_items.sqlite_mysql.desc'],
                            ['ui.stack_items.apis_webhooks.title','ui.stack_items.apis_webhooks.desc'],
                            ['ui.stack_items.deployment.title','ui.stack_items.deployment.desc'],
                        ] as $item)
                            <div class="rounded-2xl border border-black/10 dark:border-white/10 bg-white/60 dark:bg-white/5 backdrop-blur p-6">
                                <div class="font-semibold">{{ __($item[0]) }}</div>
                                <div class="text-sm mt-2 text-gray-600 dark:text-white/60">{{ __($item[1]) }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>


        {{-- ABOUT --}}
        <section id="about" class="relative py-20 sm:py-24 scroll-mt-24 overflow-hidden
            bg-gradient-to-br from-slate-50 via-white to-indigo-50
            dark:from-neutral-950 dark:via-neutral-900 dark:to-indigo-950">

            <div class="pointer-events-none absolute inset-0 border-y border-black/10 dark:border-white/10"></div>

            <div class="relative max-w-6xl mx-auto px-4">
                <div x-reveal class="transition-all duration-700 ease-out rounded-3xl border border-black/15 dark:border-white/15 bg-white/70 dark:bg-white/5 backdrop-blur p-8 sm:p-12">

                    <div class="relative pt-6">
                        <div class="absolute -top-2 left-0 text-6xl sm:text-7xl font-display font-bold
                                    text-black/10 dark:text-white/10 select-none leading-none">
                            03
                        </div>

                        <h2 class="relative text-3xl font-bold uppercase heading-tracking">
                            {{ __('ui.sections.about.title') }}
                        </h2>

                        <p class="mt-2 text-gray-600 dark:text-white/60 max-w-2xl">
                            {{ __('ui.sections.about.desc') }}
                        </p>
                    </div>

                    <div class="mt-10 grid lg:grid-cols-12 gap-8 items-start">
                        {{-- Photo --}}
                        <div class="lg:col-span-4">
                            <div class="rounded-3xl border border-black/10 dark:border-white/10 overflow-hidden bg-white/60 dark:bg-white/5">
                                <img
                                    src="{{ asset('images/me.jpeg') }}"
                                    alt="{{ __('ui.about.photo_alt') }}"
                                    class="w-full h-[360px] object-cover"
                                    loading="lazy"
                                />
                            </div>

                            <div class="mt-4 text-sm text-gray-600 dark:text-white/60">
                                <span class="font-semibold text-gray-800 dark:text-white/80">{{ __('ui.about.name') }}</span>
                                <span class="mx-2">•</span>
                                {{ __('ui.about.role') }}
                            </div>
                        </div>

                        {{-- Text --}}
                        <div class="lg:col-span-8 space-y-6">
                            <p class="text-gray-700 dark:text-white/70 leading-relaxed">
                                {{ __('ui.about.p1') }}
                            </p>

                            <p class="text-gray-700 dark:text-white/70 leading-relaxed">
                                {{ __('ui.about.p2') }}
                            </p>

                            <div class="grid sm:grid-cols-2 gap-4">
                                <div class="rounded-2xl border border-black/10 dark:border-white/10 bg-white/60 dark:bg-white/5 backdrop-blur p-5">
                                    <div class="font-semibold">{{ __('ui.about.strengths.title') }}</div>
                                    <ul class="mt-2 text-sm text-gray-600 dark:text-white/60 space-y-1">
                                        <li>• {{ __('ui.about.strengths.i1') }}</li>
                                        <li>• {{ __('ui.about.strengths.i2') }}</li>
                                        <li>• {{ __('ui.about.strengths.i3') }}</li>
                                        <li>• {{ __('ui.about.strengths.i4') }}</li>
                                    </ul>
                                </div>

                                <div class="rounded-2xl border border-black/10 dark:border-white/10 bg-white/60 dark:bg-white/5 backdrop-blur p-5">
                                    <div class="font-semibold">{{ __('ui.about.learning.title') }}</div>
                                    <ul class="mt-2 text-sm text-gray-600 dark:text-white/60 space-y-1">
                                        <li>• {{ __('ui.about.learning.i1') }}</li>
                                        <li>• {{ __('ui.about.learning.i2') }}</li>
                                        <li>• {{ __('ui.about.learning.i3') }}</li>
                                        <li>• {{ __('ui.about.learning.i4') }}</li>
                                    </ul>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-3 pt-2">
                                <a href="#projects" class="px-5 py-2.5 rounded-lg bg-black text-white dark:bg-white dark:text-black">
                                    {{ __('ui.about.cta_projects') }}
                                </a>
                                <a href="#contact" class="px-5 py-2.5 rounded-lg border border-black/15 dark:border-white/15">
                                    {{ __('ui.about.cta_contact') }}
                                </a>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </section>


        {{-- CONTACT BAND --}}
        <section id="contact" class="relative py-20 sm:py-24 scroll-mt-24 overflow-hidden
            bg-gradient-to-br from-fuchsia-50 via-white to-indigo-50
            dark:from-neutral-950 dark:via-neutral-900 dark:to-fuchsia-950">

            {{-- Noise (unique ID!) --}}
            <svg class="pointer-events-none absolute inset-0 w-full h-full opacity-[0.05] dark:opacity-[0.07]" aria-hidden="true">
                <filter id="noiseFilterContact">
                    <feTurbulence type="fractalNoise" baseFrequency="0.8" numOctaves="4" stitchTiles="stitch" />
                </filter>
                <rect width="100%" height="100%" filter="url(#noiseFilterContact)" opacity="0.18"></rect>
            </svg>

            <div class="pointer-events-none absolute inset-0">
                <div class="absolute -top-24 left-1/2 h-80 w-80 -translate-x-1/2 rounded-full bg-fuchsia-500/10 blur-3xl"></div>
                <div class="absolute bottom-0 right-10 h-80 w-80 rounded-full bg-cyan-400/10 blur-3xl"></div>
                <div class="absolute inset-0 border-y border-black/10 dark:border-white/10"></div>
            </div>

            <div class="relative max-w-6xl mx-auto px-4">
                <div x-reveal class="transition-all duration-700 ease-out rounded-3xl border border-black/10 dark:border-white/10 bg-white/70 dark:bg-white/5 backdrop-blur p-8 sm:p-12 space-y-10">
                    <div class="relative pt-6">
                        <div class="absolute -top-2 left-0 text-6xl sm:text-7xl font-display font-bold
                                    text-black/10 dark:text-white/10 select-none leading-none">
                            04
                        </div>

                        <h2 class="relative text-3xl font-bold tracking-widest uppercase heading-tracking">
                            {{ __('ui.sections.contact.title') }}
                        </h2>

                        <p class="mt-2 text-gray-600 dark:text-white/60 max-w-2xl">
                            {{ __('ui.sections.contact.desc') }}
                        </p>
                    </div>

                    <div x-reveal class="transition-all duration-700 ease-out rounded-2xl border border-black/10 dark:border-white/10 bg-white/60 dark:bg-white/5 backdrop-blur p-6 sm:p-8">
                        @include('public._contact-form')
                    </div>
                </div>

                <footer class="relative py-20 text-center">
                    <div class="absolute top-0 left-1/2 -translate-x-1/2 w-40 h-px
                                bg-gradient-to-r from-transparent via-indigo-400/50 to-transparent"></div>

                    <div class="relative z-10 flex flex-col items-center gap-6">
                        <x-application-logo class="h-20 w-auto logo-glow" />

                        <div class="text-sm text-gray-600 dark:text-white/60">
                            {{ __('ui.footer.tagline') }}
                        </div>

                        <div class="flex items-center gap-6 pt-2">
                            <a href="https://www.linkedin.com/in/nikola-savic-74a1aa26b/"
                               target="_blank"
                               class="group relative text-gray-600 dark:text-white/60 hover:text-indigo-500 transition">
                                <span class="relative z-10">{{ __('ui.footer.linkedin') }}</span>
                                <span class="absolute -inset-2 rounded-xl opacity-0 group-hover:opacity-100 transition
                                             bg-indigo-500/10 blur-lg"></span>
                            </a>

                            <a href="https://www.instagram.com/iamnikosavic"
                               target="_blank"
                               class="group relative text-gray-600 dark:text-white/60 hover:text-fuchsia-500 transition">
                                <span class="relative z-10">{{ __('ui.footer.instagram') }}</span>
                                <span class="absolute -inset-2 rounded-xl opacity-0 group-hover:opacity-100 transition
                                             bg-fuchsia-500/10 blur-lg"></span>
                            </a>
                        </div>

                        <div class="pt-6 text-xs text-gray-500 dark:text-white/40">
                            © {{ date('Y') }} {{ __('ui.footer.copyright') }}
                        </div>
                    </div>
                </footer>
            </div>
        </section>

    </div>
</x-app-layout>