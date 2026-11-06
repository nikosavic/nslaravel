<?php

namespace App\Enums;

enum GameStatus: string
{
    case Unsorted = 'unsorted';   // imported by a sync, you haven't decided yet
    case Wishlist = 'wishlist';
    case Backlog = 'backlog';
    case Playing = 'playing';
    case OnHold = 'on_hold';
    case Finished = 'finished';   // beat the story / main content
    case Completed = 'completed'; // 100%
    case Dropped = 'dropped';

    public function label(): string
    {
        return match ($this) {
            self::Unsorted => 'Unsorted',
            self::Wishlist => 'Wishlist',
            self::Backlog => 'Backlog',
            self::Playing => 'Playing',
            self::OnHold => 'On hold',
            self::Finished => 'Finished',
            self::Completed => '100% completed',
            self::Dropped => 'Dropped',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Finished, self::Completed], true);
    }

    /** Statuses a sync may flip to Playing when new playtime shows up. */
    public function canAutoPromote(): bool
    {
        return in_array($this, [self::Unsorted, self::Wishlist, self::Backlog, self::OnHold], true);
    }

    /** The colour this status always wears in the UI (public/css/gamehub.css reads it as --st). */
    public function color(): string
    {
        return match ($this) {
            self::Unsorted => '#8392d6',
            self::Wishlist => '#ff7ac6',
            self::Backlog => '#8aa2ff',
            self::Playing => '#ffc83d',
            self::OnHold => '#b49bff',
            self::Finished => '#35f0b0',
            self::Completed => '#ffe7a3',
            self::Dropped => '#ff5a52',
        };
    }
}
