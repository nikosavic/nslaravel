<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncRun extends Model
{
    protected $fillable = [
        'linked_account_id', 'status', 'started_at', 'finished_at',
        'games_seen', 'created_count', 'updated_count', 'error',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function linkedAccount(): BelongsTo
    {
        return $this->belongsTo(LinkedAccount::class);
    }
}
