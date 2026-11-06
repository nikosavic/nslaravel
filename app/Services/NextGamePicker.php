<?php

namespace App\Services;

use App\Enums\GameStatus;
use App\Models\LibraryEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * "What should I play next?" Scores every game you could pick up and explains the top few.
 * Scoring is a plain sum of readable parts (see score()), plus a small repeatable jitter from $seed,
 * so "Shuffle" changes the order between games that are close without ever surfacing a bad pick.
 */
class NextGamePicker
{
    public const MODES = [
        'any' => 'Anything',
        'continue' => 'Continue a game',
        'new' => 'Start something new',
    ];

    public const LENGTHS = [
        'any' => 'Any length',
        'short' => 'Under 10 hours',
        'medium' => '10 to 30 hours',
        'long' => 'Over 30 hours',
    ];

    private const POOL = ['playing', 'on_hold', 'backlog', 'unsorted'];

    /**
     * @return list<array{entry: LibraryEntry, score: float, reasons: list<string>}>
     */
    public function suggest(
        int $userId,
        string $mode = 'any',
        string $length = 'any',
        ?int $platformId = null,
        int $seed = 0,
        int $limit = 3,
        ?CarbonImmutable $now = null,
    ): array {
        $now ??= CarbonImmutable::now();

        $entries = LibraryEntry::with(['game', 'platform'])
            ->where('user_id', $userId)
            ->whereIn('status', self::POOL)
            ->when($platformId, fn ($q) => $q->where('platform_id', $platformId))
            ->get();

        return $this->rank($entries, $mode, $length, $seed, $now)->take($limit)->values()->all();
    }

    /**
     * Hours to clear the backlog, for the games IGDB has a time for.
     *
     * @return array{games:int, minutes:int, unknown:int}
     */
    public function backlogSummary(int $userId): array
    {
        $entries = LibraryEntry::with('game')
            ->where('user_id', $userId)
            ->whereIn('status', [GameStatus::Backlog->value, GameStatus::Playing->value, GameStatus::OnHold->value])
            ->get();

        $minutes = 0;
        $known = 0;

        foreach ($entries as $entry) {
            $main = $entry->game->ttb_main_minutes;

            if ($main) {
                $known++;
                $minutes += max(0, $main - (int) $entry->playtime_minutes);
            }
        }

        return ['games' => $entries->count(), 'minutes' => $minutes, 'unknown' => $entries->count() - $known];
    }

    /** @return Collection<int, array{entry: LibraryEntry, score: float, reasons: list<string>}> */
    public function rank(Collection $entries, string $mode, string $length, int $seed, CarbonImmutable $now): Collection
    {
        return $entries
            ->filter(fn (LibraryEntry $e) => $this->allowed($e, $mode, $length))
            ->map(function (LibraryEntry $e) use ($mode, $seed, $now) {
                [$score, $reasons] = $this->score($e, $mode, $seed, $now);

                return ['entry' => $e, 'score' => $score, 'reasons' => $reasons];
            })
            ->sortByDesc('score');
    }

    private function allowed(LibraryEntry $e, string $mode, string $length): bool
    {
        $started = in_array($e->status, [GameStatus::Playing, GameStatus::OnHold], true);

        if ($mode === 'continue' && ! $started) {
            return false;
        }

        if ($mode === 'new' && $started) {
            return false;
        }

        // Only rule a game out on length when IGDB actually told us how long it is.
        $main = $e->game->ttb_main_minutes;

        if ($length !== 'any' && $main) {
            $hours = $main / 60;
            $fits = match ($length) {
                'short' => $hours < 10,
                'medium' => $hours >= 10 && $hours <= 30,
                'long' => $hours > 30,
                default => true,
            };

            return $fits;
        }

        return true;
    }

    /** @return array{0: float, 1: list<string>} */
    private function score(LibraryEntry $e, string $mode, int $seed, CarbonImmutable $now): array
    {
        $score = 0.0;
        $reasons = [];

        $score += match ($e->status) {
            GameStatus::Playing => 40,
            GameStatus::Backlog => 28,
            GameStatus::OnHold => 22,
            default => 8, // Unsorted: you own it but never decided
        };

        if ($e->status === GameStatus::Playing) {
            $reasons[] = "You're already playing it";
        } elseif ($e->status === GameStatus::OnHold) {
            $reasons[] = 'You put it on hold';
        } elseif ($e->status === GameStatus::Backlog) {
            $reasons[] = 'It is in your backlog';
        }

        $main = $e->game->ttb_main_minutes;
        $played = (int) $e->playtime_minutes;

        // Close to the end beats far from it: finishing is the point of a backlog.
        if ($main && $played > 0 && $played < $main) {
            $done = $played / $main;
            $score += 25 * $done;

            if ($done >= 0.5) {
                $reasons[] = sprintf('About %d%% through, roughly %s left', round($done * 100), \App\Support\Format::durationText($main - $played));
            }
        } elseif ($main && $played === 0) {
            $reasons[] = 'Takes about '.\App\Support\Format::durationText($main).' to beat';
        }

        // Games that have waited longest get a nudge (up to a year).
        if ($e->status !== GameStatus::Playing && $e->created_at) {
            $days = min(365, (int) $e->created_at->diffInDays($now, true));
            $score += 15 * ($days / 365);

            if ($days >= 180) {
                $reasons[] = 'Waiting in your library for '.($days >= 365 ? 'over a year' : (int) round($days / 30).' months');
            }
        }

        // Recent play suggests momentum.
        if ($e->last_played_at && $e->last_played_at->diffInDays($now, true) <= 30 && $mode !== 'new') {
            $score += 10;
            $reasons[] = 'You played it in the last month';
        }

        if ($e->rating && $e->rating >= 8) {
            $score += 4;
        }

        // Repeatable jitter: the same seed always orders close calls the same way.
        $score += (crc32($seed.':'.$e->id) % 800) / 100; // 0 to 8

        return [round($score, 2), array_slice($reasons, 0, 3)];
    }
}
