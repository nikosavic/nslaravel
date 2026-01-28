<x-app-layout>
    <div class="max-w-3xl mx-auto px-4 py-12 space-y-6">
        <h1 class="text-3xl font-bold">Create Project</h1>

        <form method="POST" action="{{ route('admin.projects.store') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf

            {{-- Title (EN/TR) --}}
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="text-sm">Title (EN)</label>
                    <input name="title_en" class="w-full rounded border p-2" value="{{ old('title_en') }}" required>
                    @error('title_en') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                </div>

                <div>
                    <label class="text-sm">Title (TR)</label>
                    <input name="title_tr" class="w-full rounded border p-2" value="{{ old('title_tr') }}" required>
                    @error('title_tr') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                </div>
            </div>

            <div>
                <label class="text-sm">Slug (optional)</label>
                <input name="slug" class="w-full rounded border p-2" value="{{ old('slug') }}">
                @error('slug') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
            </div>

            {{-- Summary (EN/TR) --}}
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="text-sm">Summary (EN)</label>
                    <input name="summary_en" class="w-full rounded border p-2" value="{{ old('summary_en') }}">
                    @error('summary_en') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                </div>

                <div>
                    <label class="text-sm">Summary (TR)</label>
                    <input name="summary_tr" class="w-full rounded border p-2" value="{{ old('summary_tr') }}">
                    @error('summary_tr') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                </div>
            </div>

            {{-- Body (EN/TR) --}}
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="text-sm">Body (EN)</label>
                    <textarea name="body_en" rows="10" class="w-full rounded border p-2">{{ old('body_en') }}</textarea>
                    @error('body_en') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                </div>

                <div>
                    <label class="text-sm">Body (TR)</label>
                    <textarea name="body_tr" rows="10" class="w-full rounded border p-2">{{ old('body_tr') }}</textarea>
                    @error('body_tr') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="text-sm">Repo URL</label>
                    <input name="repo_url" class="w-full rounded border p-2" value="{{ old('repo_url') }}">
                    @error('repo_url') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="text-sm">Live URL</label>
                    <input name="live_url" class="w-full rounded border p-2" value="{{ old('live_url') }}">
                    @error('live_url') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                </div>
            </div>

            <div>
                <label class="text-sm">Project image (jpg/png/webp)</label>
                <input name="image" type="file" accept="image/png,image/jpeg,image/webp" class="w-full rounded border p-2 mt-2">
                @error('image') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
            </div>

            <div class="grid sm:grid-cols-3 gap-4">
                <div>
                    <label class="text-sm">Status</label>
                    <select name="status" class="w-full rounded border p-2">
                        @php($status = old('status', 'draft'))
                        <option value="draft" @selected($status==='draft')>draft</option>
                        <option value="published" @selected($status==='published')>published</option>
                    </select>
                    @error('status') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                </div>

                <div>
                    <label class="text-sm">Sort order</label>
                    <input name="sort_order" type="number" class="w-full rounded border p-2" value="{{ old('sort_order', 0) }}">
                    @error('sort_order') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                </div>

                <div class="flex items-center gap-2 pt-6">
                    <input name="featured" type="checkbox" value="1" class="rounded" @checked(old('featured'))>
                    <label class="text-sm">Featured</label>
                </div>
            </div>

            <button class="px-4 py-2 rounded bg-black text-white">Create project</button>
        </form>
    </div>
</x-app-layout>