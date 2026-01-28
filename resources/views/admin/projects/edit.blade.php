<x-app-layout>
    <div class="max-w-3xl mx-auto px-4 py-12 space-y-6">
        <h1 class="text-3xl font-bold">Edit Project</h1>

        <form method="POST" action="{{ route('admin.projects.update', $project) }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label class="text-sm">Title</label>
                <input name="title" class="w-full rounded border p-2" value="{{ old('title', $project->title) }}" required>
                @error('title') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="text-sm">Slug (optional)</label>
                <input name="slug" class="w-full rounded border p-2" value="{{ old('slug', $project->slug) }}">
                @error('slug') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="text-sm">Summary</label>
                <input name="summary" class="w-full rounded border p-2" value="{{ old('summary', $project->summary) }}">
                @error('summary') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="text-sm">Body</label>
                <textarea name="body" rows="8" class="w-full rounded border p-2">{{ old('body', $project->body) }}</textarea>
                @error('body') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="text-sm">Repo URL</label>
                    <input name="repo_url" class="w-full rounded border p-2" value="{{ old('repo_url', $project->repo_url) }}">
                    @error('repo_url') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="text-sm">Live URL</label>
                    <input name="live_url" class="w-full rounded border p-2" value="{{ old('live_url', $project->live_url) }}">
                    @error('live_url') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                </div>
            </div>

            <div>
    <label class="text-sm">Project image (jpg/png/webp)</label>

    @if($project->image_path)
        <div class="mt-2">
            <img
                src="{{ asset('storage/' . $project->image_path) }}"
                alt="Project image"
                class="h-32 rounded border object-cover"
            >
        </div>
    @endif

    <input name="image" type="file" accept="image/png,image/jpeg,image/webp" class="w-full rounded border p-2 mt-2">
    @error('image') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
</div>

            <div class="grid sm:grid-cols-3 gap-4">
                <div>
                    <label class="text-sm">Status</label>
                    <select name="status" class="w-full rounded border p-2">
                        @php($status = old('status', $project->status))
                        <option value="draft" @selected($status==='draft')>draft</option>
                        <option value="published" @selected($status==='published')>published</option>
                    </select>
                </div>

                <div>
                    <label class="text-sm">Sort order</label>
                    <input name="sort_order" type="number" class="w-full rounded border p-2" value="{{ old('sort_order', $project->sort_order) }}">
                </div>

                <div class="flex items-center gap-2 pt-6">
                    <input name="featured" type="checkbox" value="1" class="rounded"
                        @checked(old('featured', (bool) $project->featured))>
                    <label class="text-sm">Featured</label>
                </div>
            </div>

            <button class="px-4 py-2 rounded bg-black text-white">Save changes</button>
        </form>
    </div>
</x-app-layout>
