<x-app-layout>
    <div class="max-w-5xl mx-auto px-4 py-12 space-y-6">
        <h1 class="text-3xl font-bold">Projects</h1>

        @if($projects->count() === 0)
            <p class="text-gray-600">No published projects yet.</p>
        @else
        <div class="grid sm:grid-cols-2 gap-4">
    @foreach($projects as $p)
    <a href="{{ route('public.projects.show', ['locale' => request()->route('locale'), 'project' => $p->slug]) }}"

            @if($p->image_path)
                <img
                    src="{{ asset('storage/' . $p->image_path) }}"
                    alt="{{ $p->title }}"
                    class="mb-4 h-44 w-full rounded-xl border border-black/10 dark:border-white/10 object-cover"
                    loading="lazy"
                >
            @endif

            <div class="font-semibold">{{ $p->title }}</div>

            @if($p->summary)
                <div class="text-gray-600 mt-1">{{ $p->summary }}</div>
            @endif

        </a>
    @endforeach
</div>

            <div>{{ $projects->links() }}</div>
        @endif
    </div>
</x-app-layout>
