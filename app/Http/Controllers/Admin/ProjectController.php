<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $projects = Project::query()
            ->latest()
            ->paginate(20);

        return view('admin.projects.index', compact('projects'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.projects.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $this->validateProject($request);

        // ✅ Ensure legacy/base columns are always filled (DB requires them)
        $data['title'] = $data['title'] ?? $data['title_en'];
        $data['summary'] = $data['summary'] ?? ($data['summary_en'] ?? null);
        $data['body'] = $data['body'] ?? ($data['body_en'] ?? null);

        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['title_en']);
        }

        $data['featured'] = (bool) ($data['featured'] ?? false);

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('projects', 'public');
        }

        Project::create($data);

        return redirect()
            ->route('admin.projects.index')
            ->with('success', 'Project created.');
    }

    /**
     * Display the specified resource.
     * (Optional for admin — you can implement later)
     */
    public function show(Project $project)
    {
        return redirect()->route('admin.projects.edit', $project);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Project $project)
    {
        return view('admin.projects.edit', compact('project'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Project $project)
    {
        $data = $this->validateProject($request, $project->id);

        // ✅ Ensure legacy/base columns are always filled (DB requires them)
        $data['title'] = $data['title'] ?? $data['title_en'];
        $data['summary'] = $data['summary'] ?? ($data['summary_en'] ?? null);
        $data['body'] = $data['body'] ?? ($data['body_en'] ?? null);

        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['title_en']);
        }

        $data['featured'] = (bool) ($data['featured'] ?? false);

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('projects', 'public');
        }

        $project->update($data);

        return redirect()
            ->route('admin.projects.index')
            ->with('success', 'Project updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Project $project)
    {
        $project->delete();

        return redirect()
            ->route('admin.projects.index')
            ->with('success', 'Project deleted.');
    }

    private function validateProject(Request $request, ?int $ignoreId = null): array
    {
        $uniqueSlugRule = 'unique:projects,slug';
        if ($ignoreId) {
            $uniqueSlugRule .= ',' . $ignoreId;
        }

        return $request->validate([
            // base columns optional now (we will auto-fill them)
            'title' => ['nullable', 'string', 'max:120'],
            'summary' => ['nullable', 'string', 'max:280'],
            'body' => ['nullable', 'string'],

            // translations (EN required)
            'title_en' => ['required', 'string', 'max:120'],
            'title_tr' => ['nullable', 'string', 'max:120'],
            'summary_en' => ['nullable', 'string', 'max:280'],
            'summary_tr' => ['nullable', 'string', 'max:280'],
            'body_en' => ['nullable', 'string'],
            'body_tr' => ['nullable', 'string'],

            'slug' => ['nullable', 'string', 'max:140', $uniqueSlugRule],
            'repo_url' => ['nullable', 'url', 'max:255'],
            'live_url' => ['nullable', 'url', 'max:255'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'featured' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'status' => ['required', 'in:draft,published'],
        ]);
    }
}