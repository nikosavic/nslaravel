<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\ProfileData;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.gamehub')]
#[Title('Profile')]
class ProfileSettings extends Component
{
    public bool $isPublic = false;

    public string $slug = '';

    public ?string $notice = null;

    public function mount(): void
    {
        $user = Auth::user();

        $this->isPublic = (bool) $user->is_public;
        $this->slug = (string) $user->public_slug;
    }

    public function save(): void
    {
        $this->slug = strtolower(trim($this->slug));

        $this->validate([
            'slug' => [
                Rule::requiredIf($this->isPublic),
                'nullable', 'string', 'min:3', 'max:40',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('users', 'public_slug')->ignore(Auth::id()),
            ],
        ], [
            'slug.required' => 'Pick a short name for your public link.',
            'slug.regex' => 'Use lowercase letters, numbers and single hyphens.',
            'slug.unique' => 'That name is taken. Try another.',
            'slug.min' => 'Use at least 3 characters.',
        ]);

        // is_public and public_slug are not in the default User $fillable, so set them directly.
        Auth::user()->forceFill([
            'is_public' => $this->isPublic,
            'public_slug' => $this->slug !== '' ? $this->slug : null,
        ])->save();

        $this->notice = $this->isPublic ? 'Saved. Your profile is public.' : 'Saved. Your profile is private.';
    }

    public function render(ProfileData $profiles)
    {
        $user = Auth::user();
        $profile = $profiles->for($user);

        return view('livewire.profile-settings', [
            'card' => $profiles->card($profile),
            'publicUrl' => $user->is_public && $user->public_slug ? route('games.profile.public', $user->public_slug) : null,
        ]);
    }
}
