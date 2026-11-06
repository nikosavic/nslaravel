<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameExternalId extends Model
{
    protected $fillable = ['game_id', 'provider', 'external_id'];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
