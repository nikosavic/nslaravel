<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Platform extends Model
{
    protected $fillable = ['slug', 'name', 'family'];

    public function libraryEntries(): HasMany
    {
        return $this->hasMany(LibraryEntry::class);
    }

    /** Brand colour used for the small platform marker on cards. */
    public function color(): string
    {
        return match (true) {
            $this->slug === 'steam' => '#66c0f4',
            $this->family === 'playstation' => '#3d7bff',
            $this->family === 'xbox' => '#3ddc84',
            $this->family === 'nintendo' => '#ff4b55',
            default => '#a9b4e6',
        };
    }
}
