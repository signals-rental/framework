<?php

use App\Http\Controllers\Web\DocsController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    if (config('signals.installed') && ! config('signals.setup_complete')) {
        return redirect()->route('setup.wizard');
    }

    if (config('signals.setup_complete')) {
        return auth()->check()
            ? redirect()->route('dashboard')
            : redirect()->route('login');
    }

    return view('welcome');
})->name('home');

/*
|--------------------------------------------------------------------------
| Setup Wizard
|--------------------------------------------------------------------------
*/

Route::middleware(['signals.setup-required'])->group(function () {
    Volt::route('setup', 'setup.wizard')->name('setup.wizard');
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::view('dashboard', 'dashboard')
    ->middleware(['signals.setup-complete', 'auth', 'verified'])
    ->name('dashboard');

Route::middleware(['signals.setup-complete', 'auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

/*
|--------------------------------------------------------------------------
| Prototypes
|--------------------------------------------------------------------------
*/

Route::prefix('prototypes')->group(function () {
    Volt::route('document-editor', 'prototypes.document-editor')
        ->name('prototypes.document-editor');
    Volt::route('grid', 'prototypes.grid')
        ->middleware(['signals.setup-complete', 'auth'])
        ->name('prototypes.grid');
    Volt::route('availability', 'prototypes.availability')
        ->name('prototypes.availability');
    Volt::route('availability-opportunity', 'prototypes.availability-opportunity')
        ->name('prototypes.availability-opportunity');
});

/*
|--------------------------------------------------------------------------
| Documentation
|--------------------------------------------------------------------------
*/

Route::prefix('docs')->group(function () {
    Route::get('/', [DocsController::class, 'index'])->name('docs.index');
    Route::get('images/{path}', [DocsController::class, 'image'])
        ->name('docs.image')
        ->where('path', '.+');
    Route::get('{section}/{page}', [DocsController::class, 'show'])
        ->name('docs.show')
        ->where(['section' => '[a-z0-9-]+', 'page' => '[a-z0-9-]+']);
});

require __DIR__.'/auth.php';
