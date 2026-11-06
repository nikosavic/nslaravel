<?php

namespace App\Models;

use App\Enums\ProviderType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LinkedAccount extends Model
{
    protected $fillable = [
        'user_id', 'provider', 'external_id', 'display_name', 'credentials', 'last_synced_at', 'last_status',
    ];

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return [
            'provider' => ProviderType::class,
            'credentials' => 'encrypted:array', // tokens never sit in the database in clear text
            'last_synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function syncRuns(): HasMany
    {
        return $this->hasMany(SyncRun::class);
    }

    public function latestRun(): HasOne
    {
        return $this->hasOne(SyncRun::class)->latestOfMany();
    }

    public function libraryEntries(): HasMany
    {
        return $this->hasMany(LibraryEntry::class);
    }
}
