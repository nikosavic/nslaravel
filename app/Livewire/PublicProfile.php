<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\ProfileData;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.gamehub')]
class PublicProfile extends Component
{
    public string $slug = '';

    public function mount(string $slug): void
    {
        $this->slug = $slug;

        // A private or unknown profile is a plain 404, so it does not even reveal that the name exists.
        abort_unless(User::where('public_slug', $slug)->where('is_public', true)->exists(), 404);
    }

    public function render(ProfileData $profiles)
    {
        $user = User::where('public_slug', $this->slug)->where('is_public', true)->firstOrFail();

        return view('livewire.public-profile', ['profile' => $profiles->for($user)])
            ->title($user->public_slug.' on GameHub');
    }
}
