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
| Root -> redirect to locale
|--------------------------------------------------------------------------
|
| For now we default to EN. We'll add Turkey detection in the next step.
|
*/

Route::get('/', function () {
    return redirect('/en');
});

/*
|--------------------------------------------------------------------------
| Public (locale-prefixed)
|--------------------------------------------------------------------------
*/

Route::prefix('{locale}')
    ->where(['locale' => 'en|tr'])
    ->middleware(['web', 'setlocale'])
    ->group(function () {

        Route::get('/', [HomeController::class, 'index'])->name('home');

        Route::get('/projects', [PublicProjectController::class, 'index'])->name('projects.index');
        Route::get('/projects/{project:slug}', [PublicProjectController::class, 'show'])->name('projects.show');

        Route::get('/contact', [ContactController::class, 'show'])->name('contact.show');
        Route::post('/contact', [ContactController::class, 'submit'])->name('contact.submit');
    });

/*
|--------------------------------------------------------------------------
| Locale switch (session-based) - keep for now
|--------------------------------------------------------------------------
*/

Route::get('/lang/{locale}', [LocaleController::class, 'switch'])
    ->whereIn('locale', ['en', 'tr'])
    ->name('lang.switch');

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