@php($locale = request()->route('locale') ?? app()->getLocale())

<x-app-layout :title="__('ui.sections.projects.title')">
    <section class="section !pb-10">
        <div class="wrap">
            <h1 class="section-title">{{ __('ui.sections.projects.title') }}</h1>
            <p class="section-desc max-w-xl">{{ __('ui.sections.projects.desc') }}</p>

            @if ($projects->count() === 0)
                <div class="mt-12 rounded-2xl border-2 border-dashed border-ink/40 p-7 text-ink/70">
                    {!! __('ui.sections.projects.empty') !!}
                </div>
            @else
                <div class="mt-12">
                    @foreach ($projects as $p)
                        <a href="{{ route('public.projects.show', ['locale' => $locale, 'project' => $p->slug]) }}"
                           class="project-row {{ $p->image_path ? 'has-img' : '' }}">
                            @if ($p->image_path)
                                <img src="{{ asset('storage/'.$p->image_path) }}" alt="{{ $p->title }}" class="project-thumb" loading="lazy">
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

                <div class="mt-10">{{ $projects->links() }}</div>
            @endif
        </div>
    </section>

    @include('layouts.footer')
</x-app-layout>
