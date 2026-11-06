<?php

use App\Http\Controllers\ExportController;
use App\Http\Controllers\GamesEntryController;
use App\Livewire\Accounts;
use App\Livewire\Duplicates;
use App\Livewire\Library;
use App\Livewire\PlayNext;
use App\Livewire\ProfileSettings;
use App\Livewire\PublicProfile;
use App\Livewire\Stats;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| GameHub (lives under /games, route names start with "games.")
|--------------------------------------------------------------------------
| The private app (library, stats, accounts...) is for the site admin and uses the
| normal site login. Only the opt-in public profile is open to visitors.
*/
Route::prefix('games')->name('games.')->group(function () {
    // Menu target: admin -> library, visitor -> public profile (if one is switched on), otherwise sign in.
    Route::get('/', GamesEntryController::class)->name('entry');

    // Anyone with the link can open a profile its owner made public; everything else is a 404.
    Route::get('/u/{slug}', PublicProfile::class)->name('profile.public');

    Route::middleware(['auth', 'admin'])->group(function () {
        Route::get('/library', Library::class)->name('library');
        Route::get('/play-next', PlayNext::class)->name('play-next');
        Route::get('/stats', Stats::class)->name('stats');
        Route::get('/profile', ProfileSettings::class)->name('profile');
        Route::get('/duplicates', Duplicates::class)->name('duplicates');
        Route::get('/accounts', Accounts::class)->name('accounts');

        Route::get('/export/library.csv', [ExportController::class, 'library'])->name('export.library');
        Route::get('/export/stats.csv', [ExportController::class, 'stats'])->name('export.stats');
    });
});
