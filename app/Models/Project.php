<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $fillable = [
        'slug',
    
        'title',
        'title_en',
        'title_tr',
    
        'excerpt_en',
        'excerpt_tr',
    
        'body',
        'body_en',
        'body_tr',
    
        'repo_url',
        'live_url',
    
        'featured',
        'sort_order',
    
        'is_published',
        'published_at',
    ];

    protected $casts = [
        'featured' => 'boolean',
    ];
}
