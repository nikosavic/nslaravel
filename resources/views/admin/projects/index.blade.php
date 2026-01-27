<x-app-layout>

@if(session('success'))
    <div class="p-3 rounded border bg-green-50 text-green-800">{{ session('success') }}</div>
@endif

    <div class="max-w-5xl mx-auto px-4 py-12 space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-3xl font-bold">Projects</h1>
            <a href="{{ route('admin.projects.create') }}" class="px-4 py-2 rounded bg-black text-white">New</a>
        </div>

        <div class="rounded border divide-y">
            @forelse($projects as $p)
                <div class="p-4 flex items-start justify-between gap-4">
                    <div>
                        <div class="font-semibold">{{ $p->title }}</div>
                        <div class="text-sm text-gray-600">
                            {{ $p->status }} @if($p->featured) • featured @endif
                        </div>
                    </div>

                    <div class="flex gap-3 text-sm">
                        <a class="underline" href="{{ route('admin.projects.edit', $p) }}">Edit</a>
                        <form method="POST" action="{{ route('admin.projects.destroy', $p) }}">
                            @csrf
                            @method('DELETE')
                            <button class="underline text-red-600" onclick="return confirm('Delete?')">Delete</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="p-4 text-gray-600">No projects yet.</div>
            @endforelse
        </div>

        <div>{{ $projects->links() }}</div>
    </div>
</x-app-layout>
