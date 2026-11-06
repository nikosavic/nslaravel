<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    protected $fillable = [
        'igdb_id', 'title', 'cover_url', 'release_date', 'summary', 'genres', 'enriched_at',
        'ttb_main_minutes', 'ttb_complete_minutes', 'ttb_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'release_date' => 'date',
            'genres' => 'array',
            'enriched_at' => 'datetime',
            'ttb_checked_at' => 'datetime',
        ];
    }

    public function externalIds(): HasMany
    {
        return $this->hasMany(GameExternalId::class);
    }

    public function libraryEntries(): HasMany
    {
        return $this->hasMany(LibraryEntry::class);
    }
}
