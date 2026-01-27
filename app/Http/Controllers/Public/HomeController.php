<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Project;

class HomeController extends Controller
{
    public function index()
{
    $heroProject = Project::query()
        ->where('status', 'published')
        ->where('featured', true)
        ->orderBy('sort_order')
        ->latest()
        ->first();

    $featured = Project::query()
        ->where('status', 'published')
        ->when($heroProject, fn ($q) => $q->where('id', '!=', $heroProject->id))
        ->orderByDesc('featured')
        ->orderBy('sort_order')
        ->latest()
        ->take(6)
        ->get();

    return view('public.home', compact('heroProject', 'featured'));
}

}
