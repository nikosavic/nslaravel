<?php

namespace App\Livewire;

use App\Services\DuplicateMerger;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.gamehub')]
#[Title('Duplicates')]
class Duplicates extends Component
{
    public ?string $notice = null;

    public function merge(DuplicateMerger $merger, int $keepId, int $dropId): void
    {
        $this->notice = $merger->merge((int) Auth::id(), $keepId, $dropId)
            ? 'Merged. Playtime, status and ratings stay on your library entries.'
            : 'Those two could not be merged. IGDB lists them as different games, or one is no longer in your library.';
    }

    public function render(DuplicateMerger $merger)
    {
        return view('livewire.duplicates', ['groups' => $merger->groups((int) Auth::id())]);
    }
}
