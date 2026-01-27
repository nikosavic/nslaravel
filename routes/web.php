<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\ProjectController as AdminProjectController;
use App\Http\Controllers\Public\ProjectController as PublicProjectController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\ContactController;
use App\Http\Controllers\LocaleController;



Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/lang/{locale}', [LocaleController::class, 'switch'])
    ->whereIn('locale', ['en', 'tr'])
    ->name('lang.switch');

Route::get('/projects', [PublicProjectController::class, 'index'])->name('projects.index');
Route::get('/projects/{project:slug}', [PublicProjectController::class, 'show'])->name('projects.show');

Route::get('/contact', [ContactController::class, 'show'])->name('contact.show');
Route::post('/contact', [ContactController::class, 'submit'])->name('contact.submit');

Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::resource('projects', AdminProjectController::class);
    });

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    

});

require __DIR__.'/auth.php';
