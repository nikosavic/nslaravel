<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;

use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\ProjectController as PublicProjectController;
use App\Http\Controllers\Public\ContactController;
use App\Http\Controllers\Admin\ProjectController as AdminProjectController;

/*
|--------------------------------------------------------------------------
| Root -> locale redirect (auto)
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    // We'll implement detection in middleware in Step 2.
    // For now: if session has locale use it, else fallback to 'en'.
    $loc = session('locale', 'en');
    return redirect()->to('/' . $loc);
})->name('root');

/*
|--------------------------------------------------------------------------
| Public (locale-prefixed)
|--------------------------------------------------------------------------
|
| Named with locale prefix: en.home, tr.home, en.projects.index, ...
|
*/
Route::prefix('{locale}')
    ->where(['locale' => 'en|tr'])
    ->middleware(['web'])
    ->group(function () {

        Route::get('/', [HomeController::class, 'index'])->name('home');

        Route::get('/projects', [PublicProjectController::class, 'index'])->name('projects.index');
        Route::get('/projects/{project:slug}', [PublicProjectController::class, 'show'])->name('projects.show');

        Route::get('/contact', [ContactController::class, 'show'])->name('contact.show');
        Route::post('/contact', [ContactController::class, 'submit'])->name('contact.submit');
    });

/*
|--------------------------------------------------------------------------
| Locale switch (session-based)
|--------------------------------------------------------------------------
*/
Route::get('/lang/{locale}', [LocaleController::class, 'switch'])
    ->whereIn('locale', ['en', 'tr'])
    ->name('lang.switch');

/*
|--------------------------------------------------------------------------
| Compatibility route names (temporary)
|--------------------------------------------------------------------------
|
| Keeps existing route('home') / route('projects.index') calls working
| by redirecting to /{session-locale}/...
|
*/
Route::middleware(['web'])->group(function () {
    Route::get('/projects', fn () => redirect()->to('/' . session('locale', 'en') . '/projects'))
        ->name('projects.index');

    Route::get('/projects/{project:slug}', fn ($slug) => redirect()->to('/' . session('locale', 'en') . '/projects/' . $slug))
        ->name('projects.show');

    Route::get('/contact', fn () => redirect()->to('/' . session('locale', 'en') . '/contact'))
        ->name('contact.show');

    Route::post('/contact', fn () => redirect()->to('/' . session('locale', 'en') . '/contact'))
        ->name('contact.submit');

    Route::get('/home', fn () => redirect()->to('/' . session('locale', 'en')))
        ->name('home');
});

/*
|--------------------------------------------------------------------------
| Admin
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::resource('projects', AdminProjectController::class);
    });

/*
|--------------------------------------------------------------------------
| Authenticated pages
|--------------------------------------------------------------------------
*/
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';