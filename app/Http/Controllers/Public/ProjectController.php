<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Project;

class ProjectController extends Controller
{
    public function index()
    {
        $projects = Project::query()
            ->where('status', 'published')
            ->orderBy('sort_order')
            ->latest()
            ->paginate(12);

        return view('public.projects.index', compact('projects'));
    }

    public function show(Project $project)
    {
        abort_unless($project->status === 'published', 404);

        return view('public.projects.show', compact('project'));
    }
}
