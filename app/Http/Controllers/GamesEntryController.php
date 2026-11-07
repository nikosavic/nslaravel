<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * The "GameHub" menu entry points here.
 *  - the site admin goes straight to the library,
 *  - a visitor sees the admin's public profile right here at /games when one is switched on,
 *  - otherwise a guest is asked to sign in and anyone else gets a 404.
 */
class GamesEntryController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        if ($user && $user->role === 'admin') {
            return redirect()->route('games.library');
        }

        $owner = User::query()
            ->where('role', 'admin')
            ->where('is_public', true)
            ->whereNotNull('public_slug')
            ->orderBy('id')
            ->first();

        if ($owner) {
            return view('games.public', ['slug' => $owner->public_slug]);
        }

        abort_if($user, 404);

        return redirect()->guest(route('login'));
    }
}
