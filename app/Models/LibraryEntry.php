<?php

namespace App\Models;

use App\Enums\GameStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LibraryEntry extends Model
{
    protected $fillable = [
        'user_id', 'game_id', 'platform_id', 'linked_account_id', 'status', 'playtime_minutes',
        'last_played_at', 'started_at', 'finished_at', 'rating', 'notes', 'source',
        'achievements_unlocked', 'achievements_total', 'trophies', 'achievements_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => GameStatus::class,
            'last_played_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'trophies' => 'array',
            'achievements_synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function linkedAccount(): BelongsTo
    {
        return $this->belongsTo(LinkedAccount::class);
    }

    /** Change the status and keep started_at / finished_at consistent with it. */
    public function applyStatus(GameStatus $status): void
    {
        $this->status = $status;

        if ($status === GameStatus::Playing && ! $this->started_at) {
            $this->started_at = now();
        }

        if ($status->isFinal() && ! $this->finished_at) {
            $this->finished_at = now();
        }

        $this->save();
    }

    /** Percent of achievements unlocked, or null when the game has none (or they were never read). */
    public function achievementPercent(): ?int
    {
        $total = (int) $this->achievements_total;

        return $total > 0 ? (int) floor(((int) $this->achievements_unlocked) / $total * 100) : null;
    }

    public function hasPlatinum(): bool
    {
        return (int) ($this->trophies['platinum'] ?? 0) > 0;
    }

    public function playtimeHours(): float
    {
        return round($this->playtime_minutes / 60, 1);
    }

    /**
     * Playtime split into a number and its unit for display: ['44.7', 'hours'], ['2', 'minutes'].
     * Null when the game has never been played.
     *
     * @return array{0:string,1:string}|null
     */
    public function playtimeParts(): ?array
    {
        $minutes = (int) $this->playtime_minutes;

        if ($minutes <= 0) {
            return null;
        }

        if ($minutes < 60) {
            return [(string) $minutes, $minutes === 1 ? 'minute' : 'minutes'];
        }

        $hours = round($minutes / 60, 1);

        return [rtrim(rtrim(number_format($hours, 1), '0'), '.'), $hours == 1.0 ? 'hour' : 'hours'];
    }
}
