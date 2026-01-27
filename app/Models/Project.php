<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'summary',
        'body',
        'repo_url',
        'live_url',
        'featured',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'featured' => 'boolean',
    ];
}
