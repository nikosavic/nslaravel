@php($locale = request()->route('locale') ?? app()->getLocale())

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
                <p class="mt-6 max-w-2xl text-xl leading-relaxed text-ink/75">{{ $project->summary }}</p>
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

            @if ($project->image_path)
                <img src="{{ asset('storage/'.$project->image_path) }}" alt="{{ $project->title }}"
                     class="mt-12 w-full max-w-5xl rounded-3xl border-2 border-ink object-cover" loading="lazy">
            @endif

            <div class="case-study mt-12">
                @if ($project->body)
                    {!! nl2br(e($project->body)) !!}
                @else
                    <p class="text-ink/70">Case study coming soon.</p>
                @endif
            </div>
        </div>
    </article>

    @include('layouts.footer')
</x-app-layout>
