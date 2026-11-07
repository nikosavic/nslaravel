@php
    $locale = request()->route('locale') ?? app()->getLocale();
    $case = \App\Support\CaseStudy::parse($project->body);
    $facts = $case['facts'];
    $hasSide = $facts || $project->live_url || $project->repo_url;
@endphp

<x-app-layout :title="$project->title">
    <article class="section !pb-10">
        <div class="wrap">
            <a href="{{ route('public.projects.index', ['locale' => $locale]) }}"
               class="inline-flex items-center gap-2 text-[15px] font-semibold text-ink/70 hover:text-ink">
                <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 8H3M7 4 3 8l4 4"/></svg>
                {{ __('ui.sections.projects.title') }}
            </a>

            <h1 class="mt-8 max-w-4xl font-display font-extrabold" style="font-size: clamp(2.6rem, 7vw, 5.5rem); line-height: .92; letter-spacing: -0.04em;">{{ $project->title }}</h1>

            @if ($project->summary)
                <p class="mt-6 max-w-2xl text-xl leading-relaxed text-ink/75 sm:text-2xl sm:leading-snug">{{ $project->summary }}</p>
            @endif

            @if ($project->image_path)
                <img src="{{ asset('storage/'.$project->image_path) }}" alt="{{ $project->title }}"
                     class="mt-12 w-full rounded-3xl border-2 border-ink object-cover" loading="lazy">
            @endif

            <div class="section-grid mt-14 sm:mt-20">
                @if ($hasSide)
                    <aside class="section-head">
                        <div>
                            @if ($facts)
                                <dl class="grid grid-cols-2 gap-x-6 gap-y-6 lg:grid-cols-1">
                                    @foreach ($facts as [$key, $value])
                                        <div class="fact">
                                            <dt>{{ $key }}</dt>
                                            <dd>{{ $value }}</dd>
                                        </div>
                                    @endforeach
                                </dl>
                            @endif

                            @if ($project->repo_url || $project->live_url)
                                <div class="mt-8 flex flex-wrap gap-3">
                                    @if ($project->live_url)
                                        <a class="btn btn-solid" href="{{ $project->live_url }}" target="_blank" rel="noopener noreferrer">Live</a>
                                    @endif
                                    @if ($project->repo_url)
                                        <a class="btn btn-line" href="{{ $project->repo_url }}" target="_blank" rel="noopener noreferrer">Repo</a>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </aside>
                @endif

                <div @class(['section-body', 'lg:col-span-12' => ! $hasSide])>
                    <div class="case-study">
                        @if ($case['html'] !== '')
                            {!! $case['html'] !!}
                        @else
                            <p class="text-ink/70">Case study coming soon.</p>
                        @endif
                    </div>

                    <div class="mt-16 flex max-w-[40rem] flex-wrap items-center gap-3 border-t-2 border-ink pt-8">
                        <a href="{{ route('public.projects.index', ['locale' => $locale]) }}" class="btn btn-line">{{ __('ui.sections.projects.view_all') }}</a>
                        <a href="{{ route('public.home', ['locale' => $locale]) }}#contact" class="btn btn-solid">{{ __('ui.about.cta_contact') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </article>

    @include('layouts.footer')
</x-app-layout>
