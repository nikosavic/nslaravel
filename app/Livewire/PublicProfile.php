<?php

namespace App\Livewire;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\LibraryEntry;
use App\Models\Platform;
use App\Models\User;
use App\Services\ProfileData;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The public face of GameHub: a summary plus the whole library, read only. Shown at /games for visitors
 * and at /games/u/{slug}. Notes, emails, linked account names and dates of play are never part of it.
 */
#[Layout('components.layouts.gamehub')]
class PublicProfile extends Component
{
    private const PAGE = 24;

    #[Locked]
    public string $slug = '';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $platform = '';

    #[Url(except: 'recent')]
    public string $sort = 'recent';

    public int $limit = self::PAGE;

    public function mount(string $slug): void
    {
        $this->slug = $slug;

        // A private or unknown profile is a plain 404, so it does not even reveal that the name exists.
        abort_unless($this->owner() !== null, 404);
    }

    public function updated(string $name): void
    {
        if (in_array($name, ['search', 'status', 'platform', 'sort'], true)) {
            $this->limit = self::PAGE;
        }
    }

    public function more(): void
    {
        $this->limit += self::PAGE;
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'status', 'platform']);
        $this->limit = self::PAGE;
    }

    private function owner(): ?User
    {
        return User::where('public_slug', $this->slug)->where('is_public', true)->first();
    }

    public function render(ProfileData $profiles)
    {
        $user = $this->owner() ?? abort(404);

        $status = GameStatus::tryFrom($this->status)?->value;
        $platformId = ctype_digit($this->platform) ? (int) $this->platform : null;

        $rows = LibraryEntry::query()
            ->with(['game', 'platform'])
            ->where('user_id', $user->id)
            ->when($this->search !== '', fn ($q) => $q->whereHas('game', fn ($g) => $g->where('title', 'like', '%'.$this->search.'%')))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($platformId, fn ($q) => $q->where('platform_id', $platformId))
            ->tap(fn ($q) => match ($this->sort) {
                'rating' => $q->orderByRaw('rating IS NULL')->orderByDesc('rating')->orderByDesc('playtime_minutes'),
                'trophies' => $q->orderByDesc('achievements_unlocked')->orderByDesc('playtime_minutes'),
                'playtime' => $q->orderByDesc('playtime_minutes'),
                'title' => $q->orderBy(Game::select('title')->whereColumn('games.id', 'library_entries.game_id')),
                default => $q->orderByRaw('last_played_at IS NULL')->orderByDesc('last_played_at')->orderByDesc('id'),
            })
            ->take($this->limit + 1)
            ->get();

        $profile = $profiles->for($user);

        return view('livewire.public-profile', [
            'profile' => $profile,
            'entries' => $rows->take($this->limit),
            'hasMore' => $rows->count() > $this->limit,
            'platforms' => Platform::whereIn('id', LibraryEntry::where('user_id', $user->id)->select('platform_id'))->orderBy('name')->get(),
            'filtering' => $this->search !== '' || $status !== null || $platformId !== null,
            'statuses' => GameStatus::cases(),
        ]);
    }
}
