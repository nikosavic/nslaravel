<?php

namespace App\Livewire;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\LibraryEntry;
use App\Models\Platform;
use App\Services\DuplicateMerger;
use App\Services\GameResolver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.gamehub')]
#[Title('Library')]
class Library extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $platform = '';

    #[Url(except: 'recent')]
    public string $sort = 'recent';

    // "Add a game" form
    public string $newTitle = '';

    public string $newPlatformId = '';

    public string $newStatus = 'backlog';

    public function updated(string $name): void
    {
        if (in_array($name, ['search', 'status', 'platform', 'sort'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function platforms()
    {
        return Platform::orderBy('name')->get();
    }

    /** @return array{byStatus: array<string,int>, total: int, hours: int} */
    #[Computed]
    public function counts(): array
    {
        $rows = LibraryEntry::query()
            ->where('user_id', Auth::id())
            ->selectRaw('status, COUNT(*) AS entries, COALESCE(SUM(playtime_minutes), 0) AS minutes')
            ->groupBy('status')
            ->get();

        return [
            'byStatus' => $rows->mapWithKeys(fn ($row) => [$row->getRawOriginal('status') => (int) $row->entries])->all(),
            'total' => (int) $rows->sum('entries'),
            'hours' => (int) round($rows->sum('minutes') / 60),
        ];
    }

    #[Computed]
    public function duplicateCount(): int
    {
        return app(DuplicateMerger::class)->count((int) Auth::id());
    }

    public function addGame(GameResolver $resolver): void
    {
        $data = $this->validate([
            'newTitle' => ['required', 'string', 'max:200'],
            'newPlatformId' => ['required', 'exists:platforms,id'],
            'newStatus' => ['required', Rule::enum(GameStatus::class)],
        ]);

        $game = $resolver->findOrCreateByTitle($data['newTitle']);

        $entry = LibraryEntry::firstOrNew([
            'user_id' => Auth::id(),
            'game_id' => $game->id,
            'platform_id' => (int) $data['newPlatformId'],
        ]);

        if ($entry->exists) {
            $this->addError('newTitle', 'That game is already in your library on this platform.');

            return;
        }

        $entry->source = 'manual';
        $entry->applyStatus(GameStatus::from($data['newStatus']));

        $this->reset('newTitle');
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'status', 'platform']);
        $this->resetPage();
    }

    public function setStatus(int $entryId, string $status): void
    {
        $newStatus = GameStatus::tryFrom($status);
        $entry = $this->ownEntry($entryId);

        if ($newStatus && $entry) {
            $entry->applyStatus($newStatus);
        }
    }

    public function setRating(int $entryId, int|string|null $rating): void
    {
        $entry = $this->ownEntry($entryId);
        $value = ($rating === null || $rating === '') ? null : (int) $rating;

        if (! $entry || ($value !== null && ($value < 1 || $value > 10))) {
            return;
        }

        $entry->update(['rating' => $value]);
    }

    public function remove(int $entryId): void
    {
        $this->ownEntry($entryId)?->delete();
    }

    private function ownEntry(int $id): ?LibraryEntry
    {
        return LibraryEntry::where('user_id', Auth::id())->find($id);
    }

    public function render()
    {
        $entries = LibraryEntry::query()
            ->with(['game', 'platform'])
            ->where('user_id', Auth::id())
            ->when($this->search !== '', fn ($query) => $query->whereHas(
                'game',
                fn ($game) => $game->where('title', 'like', '%'.$this->search.'%')
            ))
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->platform !== '', fn ($query) => $query->where('platform_id', $this->platform))
            ->tap(fn ($query) => match ($this->sort) {
                'playtime' => $query->orderByDesc('playtime_minutes'),
                'title' => $query->orderBy(Game::select('title')->whereColumn('games.id', 'library_entries.game_id')),
                'added' => $query->orderByDesc('id'),
                default => $query->orderByRaw('last_played_at IS NULL')->orderByDesc('last_played_at'),
            })
            ->paginate(25);

        // The "Now playing" hero only shows on the unfiltered view.
        $filtering = $this->search !== '' || $this->status !== '' || $this->platform !== '';
        $hero = null;

        if (! $filtering) {
            $hero = LibraryEntry::query()
                ->with(['game', 'platform'])
                ->where('user_id', Auth::id())
                ->where('status', GameStatus::Playing->value)
                ->orderByRaw('last_played_at IS NULL')
                ->orderByDesc('last_played_at')
                ->orderByDesc('id')
                ->first();
        }

        $morePlaying = $hero ? max(0, ($this->counts['byStatus'][GameStatus::Playing->value] ?? 0) - 1) : 0;

        return view('livewire.library', [
            'entries' => $entries,
            'hero' => $hero,
            'morePlaying' => $morePlaying,
        ]);
    }
}
