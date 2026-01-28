<x-app-layout>
    <div class="max-w-3xl mx-auto px-4 py-12 space-y-6">
        <a href="{{ route('projects.index') }}" class="text-sm text-gray-600 hover:underline">← Back</a>

        <h1 class="text-3xl font-bold">{{ $project->title }}</h1>

        @if($project->image_path)
    <div class="mt-6">
        <img
            src="{{ asset('storage/' . $project->image_path) }}"
            alt="{{ $project->title }}"
            class="w-full max-w-5xl rounded-2xl border border-black/10 dark:border-white/10 object-cover"
            loading="lazy"
        >
    </div>
@endif

        @if($project->summary)
            <p class="text-gray-600">{{ $project->summary }}</p>
        @endif

        <div class="flex gap-3 text-sm">
            @if($project->repo_url)
                <a class="underline" href="{{ $project->repo_url }}" target="_blank" rel="noreferrer">Repo</a>
            @endif
            @if($project->live_url)
                <a class="underline" href="{{ $project->live_url }}" target="_blank" rel="noreferrer">Live</a>
            @endif
        </div>

        @if($project->body)
            <div class="prose max-w-none">
                {!! nl2br(e($project->body)) !!}
            </div>
        @else
            <p class="text-gray-600">Case study coming soon.</p>
        @endif
    </div>
</x-app-layout>
