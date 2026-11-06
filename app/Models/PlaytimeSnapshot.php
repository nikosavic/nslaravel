<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaytimeSnapshot extends Model
{
    protected $fillable = ['library_entry_id', 'taken_on', 'minutes'];

    protected function casts(): array
    {
        return ['taken_on' => 'date'];
    }

    public function libraryEntry(): BelongsTo
    {
        return $this->belongsTo(LibraryEntry::class);
    }
}
