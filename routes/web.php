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
    Volt::route('/', 'prototypes.index')
        ->name('prototypes.index');
    Volt::route('document-editor', 'prototypes.document-editor')
        ->name('prototypes.document-editor');
    Volt::route('grid', 'prototypes.grid')
        ->middleware(['signals.setup-complete', 'auth'])
        ->name('prototypes.grid');
    Volt::route('availability', 'prototypes.availability')
        ->name('prototypes.availability');
    Volt::route('availability-opportunity', 'prototypes.availability-opportunity')
        ->name('prototypes.availability-opportunity');
    Volt::route('workflow-editor', 'prototypes.workflow-editor')
        ->name('prototypes.workflow-editor');
    Volt::route('workflow-editor-split', 'prototypes.workflow-editor-split')
        ->name('prototypes.workflow-editor-split');
    Volt::route('workflow-editor-timeline', 'prototypes.workflow-editor-timeline')
        ->name('prototypes.workflow-editor-timeline');
    Volt::route('workflow-editor-minimal', 'prototypes.workflow-editor-minimal')
        ->name('prototypes.workflow-editor-minimal');
    Volt::route('field-registry', 'prototypes.field-registry')
        ->name('prototypes.field-registry');
    Volt::route('notification-admin', 'prototypes.notification-admin')
        ->name('prototypes.notification-admin');
    Volt::route('rate-engine', 'prototypes.rate-engine')
        ->name('prototypes.rate-engine');
    Volt::route('reporting', 'prototypes.reporting')
        ->name('prototypes.reporting');
    Volt::route('custom-views', 'prototypes.custom-views')
        ->name('prototypes.custom-views');
    Volt::route('import-export', 'prototypes.import-export')
        ->name('prototypes.import-export');
    Volt::route('permissions', 'prototypes.permissions')
        ->name('prototypes.permissions');
    Volt::route('settings-admin', 'prototypes.settings-admin')
        ->name('prototypes.settings-admin');
    Volt::route('shortage-resolution', 'prototypes.shortage-resolution')
        ->name('prototypes.shortage-resolution');
    Volt::route('plugin-system', 'prototypes.plugin-system')
        ->name('prototypes.plugin-system');
    Volt::route('opportunity-lifecycle', 'prototypes.opportunity-lifecycle')
        ->name('prototypes.opportunity-lifecycle');
    Route::redirect('component-reference', '/docs/development/library')
        ->name('prototypes.component-reference');
});

/*
|--------------------------------------------------------------------------
| Documentation
|--------------------------------------------------------------------------
*/

Route::prefix('docs')->group(function () {
    Route::get('/', [DocsController::class, 'index'])->name('docs.index');
    Route::get('changelog', [DocsController::class, 'changelog'])->name('docs.changelog');
    Route::get('sitemap.xml', [DocsController::class, 'sitemap'])->name('docs.sitemap');
    Route::get('robots.txt', [DocsController::class, 'robots'])->name('docs.robots');
    Route::get('images/{path}', [DocsController::class, 'image'])
        ->name('docs.image')
        ->where('path', '.+');
    Route::get('{section}/{page}', [DocsController::class, 'show'])
        ->name('docs.show')
        ->where(['section' => '[a-z0-9-]+', 'page' => '[a-z0-9-]+']);
});

require __DIR__.'/auth.php';
