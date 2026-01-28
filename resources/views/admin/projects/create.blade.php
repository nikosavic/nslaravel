<x-app-layout>
    <div class="max-w-3xl mx-auto px-4 py-12 space-y-6">
        <h1 class="text-3xl font-bold">Create Project</h1>

        <form method="POST" action="{{ route('admin.projects.store') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf

            @if ($errors->any())
    <div class="p-3 rounded border border-red-200 bg-red-50 text-red-800">
        <div class="font-semibold">Please fix the errors below:</div>
        <ul class="list-disc pl-5 mt-2 text-sm">
            @foreach ($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
@endif

            {{-- Title (EN/TR) --}}
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                <label class="text-sm text-gray-700 dark:text-white/80">Title (EN)</label>
                    <input name="title_en" class="w-full rounded border p-2 bg-white text-gray-900 placeholder:text-gray-400
       dark:bg-neutral-900 dark:text-white dark:placeholder:text-white/40 dark:border-white/10" value="{{ old('title_en') }}" required>
                    @error('title_en') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                </div>

                <div>
                <label class="text-sm text-gray-700 dark:text-white/80">Title (TR)</label>
                    <input name="title_tr" class="w-full rounded border p-2 bg-white text-gray-900 placeholder:text-gray-400
       dark:bg-neutral-900 dark:text-white dark:placeholder:text-white/40 dark:border-white/10" value="{{ old('title_tr') }}" required>
                    @error('title_tr') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                </div>
            </div>

            <div>
            <label class="text-sm text-gray-700 dark:text-white/80">Slug (optional)</label>
                <input name="slug" class="w-full rounded border p-2 bg-white text-gray-900 placeholder:text-gray-400
       dark:bg-neutral-900 dark:text-white dark:placeholder:text-white/40 dark:border-white/10" value="{{ old('slug') }}">
                @error('slug') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
            </div>

            {{-- Summary (EN/TR) --}}
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                <label class="text-sm text-gray-700 dark:text-white/80">Summary (EN)</label>
                    <input name="summary_en" class="w-full rounded border p-2 bg-white text-gray-900 placeholder:text-gray-400
       dark:bg-neutral-900 dark:text-white dark:placeholder:text-white/40 dark:border-white/10" value="{{ old('summary_en') }}">
                    @error('summary_en') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                </div>

                <div>
                <label class="text-sm text-gray-700 dark:text-white/80">Summary (TR)</label>
                    <input name="summary_tr" class="w-full rounded border p-2 bg-white text-gray-900 placeholder:text-gray-400
       dark:bg-neutral-900 dark:text-white dark:placeholder:text-white/40 dark:border-white/10" value="{{ old('summary_tr') }}">
                    @error('summary_tr') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                </div>
            </div>

            {{-- Body (EN/TR) --}}
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                <label class="text-sm text-gray-700 dark:text-white/80">Body (EN)</label>
                    <textarea name="body_en" rows="10" class="w-full rounded border p-2 bg-white text-gray-900 placeholder:text-gray-400
       dark:bg-neutral-900 dark:text-white dark:placeholder:text-white/40 dark:border-white/10">{{ old('body_en') }}</textarea>
                    @error('body_en') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                </div>

                <div>
                <label class="text-sm text-gray-700 dark:text-white/80">Body (TR)</label>
                    <textarea name="body_tr" rows="10" class="w-full rounded border p-2 bg-white text-gray-900 placeholder:text-gray-400
       dark:bg-neutral-900 dark:text-white dark:placeholder:text-white/40 dark:border-white/10">{{ old('body_tr') }}</textarea>
                    @error('body_tr') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                <label class="text-sm text-gray-700 dark:text-white/80">Repo URL</label>
                    <input name="repo_url" class="w-full rounded border p-2 bg-white text-gray-900 placeholder:text-gray-400
       dark:bg-neutral-900 dark:text-white dark:placeholder:text-white/40 dark:border-white/10" value="{{ old('repo_url') }}">
                    @error('repo_url') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                </div>
                <div>
                <label class="text-sm text-gray-700 dark:text-white/80">Live URL</label>
                    <input name="live_url" class="w-full rounded border p-2 bg-white text-gray-900 placeholder:text-gray-400
       dark:bg-neutral-900 dark:text-white dark:placeholder:text-white/40 dark:border-white/10" value="{{ old('live_url') }}">
                    @error('live_url') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                </div>
            </div>

            <div>
            <label class="text-sm text-gray-700 dark:text-white/80">Project image (jpg/png/webp)</label>
                <input name="image" type="file" accept="image/png,image/jpeg,image/webp" class="w-full rounded border p-2 bg-white text-gray-900 placeholder:text-gray-400
       dark:bg-neutral-900 dark:text-white dark:placeholder:text-white/40 dark:border-white/10">
                @error('image') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
            </div>

            <div class="grid sm:grid-cols-3 gap-4">
                <div>
                <label class="text-sm text-gray-700 dark:text-white/80">Status</label>
                    <select name="status" class="w-full rounded border p-2 bg-white text-gray-900 placeholder:text-gray-400
       dark:bg-neutral-900 dark:text-white dark:placeholder:text-white/40 dark:border-white/10">
                        @php($status = old('status', 'draft'))
                        <option value="draft" @selected($status==='draft')>draft</option>
                        <option value="published" @selected($status==='published')>published</option>
                    </select>
                    @error('status') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                </div>

                <div>
                <label class="text-sm text-gray-700 dark:text-white/80">Sort order</label>
                    <input name="sort_order" type="number" class="w-full rounded border p-2 bg-white text-gray-900 placeholder:text-gray-400
       dark:bg-neutral-900 dark:text-white dark:placeholder:text-white/40 dark:border-white/10" value="{{ old('sort_order', 0) }}">
                    @error('sort_order') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                </div>

                <div class="flex items-center gap-2 pt-6">
                    <input name="featured" type="checkbox" value="1" class="rounded" @checked(old('featured'))>
                    <label class="text-sm text-gray-700 dark:text-white/80">Featured</label>
                </div>
            </div>

            <button class="px-4 py-2 rounded bg-black text-white">Create project</button>
        </form>
    </div>
</x-app-layout>