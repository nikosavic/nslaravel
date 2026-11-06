<?php

namespace App\Livewire;

use App\Enums\GameStatus;
use App\Models\LibraryEntry;
use App\Models\Platform;
use App\Services\NextGamePicker;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.gamehub')]
#[Title('Play next')]
class PlayNext extends Component
{
    #[Url(except: 'any')]
    public string $mode = 'any';

    #[Url(except: 'any')]
    public string $length = 'any';

    #[Url(except: '')]
    public string $platform = '';

    public int $seed = 0;

    public function shuffle(): void
    {
        $this->seed = random_int(1, 1_000_000);
    }

    /** "Start playing" on a suggestion: it becomes the Now playing game. */
    public function start(int $entryId): void
    {
        LibraryEntry::where('user_id', Auth::id())->find($entryId)?->applyStatus(GameStatus::Playing);
    }

    public function render(NextGamePicker $picker)
    {
        $mode = array_key_exists($this->mode, NextGamePicker::MODES) ? $this->mode : 'any';
        $length = array_key_exists($this->length, NextGamePicker::LENGTHS) ? $this->length : 'any';

        return view('livewire.play-next', [
            'modes' => NextGamePicker::MODES,
            'lengths' => NextGamePicker::LENGTHS,
            'platforms' => Platform::orderBy('name')->get(),
            'picks' => $picker->suggest((int) Auth::id(), $mode, $length, $this->platform !== '' ? (int) $this->platform : null, $this->seed),
            'backlog' => $picker->backlogSummary((int) Auth::id()),
        ]);
    }
}
