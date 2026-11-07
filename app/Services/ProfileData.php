<?php

namespace App\Services;

use App\Enums\GameStatus;
use App\Enums\StatsPeriod;
use App\Models\LibraryEntry;
use App\Models\User;

/**
 * Everything the public profile page and the share card show: game titles, covers, hours, ratings, trophy counts
 * and the handle. Never the email, linked account names, notes or dates of play.
 */
class ProfileData
{
    public function __construct(private StatsService $stats)
    {
    }

    /**
     * @return array{
     *   handle: ?string, games: int, hours: int, finished: int, yearHours: int,
     *   statuses: list<array{key:string,label:string,color:string,count:int}>,
     *   playing: list<LibraryEntry>, recentlyFinished: list<LibraryEntry>, top: list<LibraryEntry>,
     *   achievements: array{unlocked:int,total:int}, platinums: int, avgRating: ?float, ratedCount: int,
     *   topRated: list<LibraryEntry>
     * }
     */
    public function for(User $user): array
    {
        $entries = LibraryEntry::with(['game', 'platform'])->where('user_id', $user->id)->get();

        $statuses = [];

        foreach (GameStatus::cases() as $status) {
            $count = $entries->where('status', $status)->count();

            if ($count > 0) {
                $statuses[] = ['key' => $status->value, 'label' => $status->label(), 'color' => $status->color(), 'count' => $count];
            }
        }

        $finished = $entries->filter(fn (LibraryEntry $e) => $e->status->isFinal());

        $withAchievements = $entries->where('achievements_total', '>', 0);
        $rated = $entries->filter(fn (LibraryEntry $e) => (int) $e->rating > 0);

        return [
            'handle' => $user->public_slug,
            'games' => $entries->count(),
            'hours' => (int) round($entries->sum('playtime_minutes') / 60),
            'finished' => $finished->count(),
            'yearHours' => (int) round($this->stats->report($user->id, StatsPeriod::Year)['minutes'] / 60),
            'statuses' => $statuses,
            'playing' => $entries->where('status', GameStatus::Playing)->sortByDesc('last_played_at')->take(4)->values()->all(),
            'recentlyFinished' => $finished->sortByDesc('finished_at')->take(8)->values()->all(),
            'top' => $entries->sortByDesc('playtime_minutes')->filter(fn ($e) => $e->playtime_minutes > 0)->take(6)->values()->all(),
            'achievements' => [
                'unlocked' => (int) $withAchievements->sum('achievements_unlocked'),
                'total' => (int) $withAchievements->sum('achievements_total'),
            ],
            'platinums' => $entries->filter(fn (LibraryEntry $e) => $e->hasPlatinum())->count(),
            'avgRating' => $rated->isEmpty() ? null : round((float) $rated->avg('rating'), 1),
            'ratedCount' => $rated->count(),
            'topRated' => $rated->sortBy([['rating', 'desc'], ['playtime_minutes', 'desc']])->take(6)->values()->all(),
        ];
    }

    /** The slim, JSON-safe subset the share card draws. */
    public function card(array $profile): array
    {
        return [
            'handle' => $profile['handle'],
            'games' => $profile['games'],
            'hours' => $profile['hours'],
            'finished' => $profile['finished'],
            'yearHours' => $profile['yearHours'],
            'statuses' => $profile['statuses'],
            'top' => array_map(fn (LibraryEntry $e) => [
                'title' => $e->game->title,
                'hours' => round($e->playtime_minutes / 60, 1),
            ], array_slice($profile['top'], 0, 3)),
        ];
    }
}
