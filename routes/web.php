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
    ->middleware(['signals.setup-complete', 'auth', 'verified', '2fa'])
    ->name('dashboard');

Route::middleware(['signals.setup-complete', 'auth', '2fa'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

/*
|--------------------------------------------------------------------------
| Invitation Acceptance (signed URL, no auth required)
|--------------------------------------------------------------------------
*/

Route::middleware(['signals.setup-complete'])->group(function () {
    Volt::route('invitation/{user}', 'auth.accept-invitation')
        ->name('invitation.accept')
        ->middleware('signed');
});

/*
|--------------------------------------------------------------------------
| Admin Panel
|--------------------------------------------------------------------------
*/

Route::middleware(['signals.setup-complete', 'auth', '2fa', 'admin'])->group(function () {
    Route::redirect('admin', 'admin/settings/company');
    Route::redirect('admin/settings', 'admin/settings/company');

    // Account
    Volt::route('admin/settings/company', 'admin.settings.company')->name('admin.settings.company');
    Volt::route('admin/settings/stores', 'admin.settings.stores')->name('admin.settings.stores');
    Volt::route('admin/settings/branding', 'admin.settings.branding')->name('admin.settings.branding');
    Volt::route('admin/settings/modules', 'admin.settings.modules')->name('admin.settings.modules');

    // Users & Security
    Volt::route('admin/settings/users', 'admin.settings.users')->name('admin.settings.users');
    Volt::route('admin/settings/users/{user}/edit', 'admin.settings.user-form')->name('admin.settings.users.edit');
    Volt::route('admin/settings/roles', 'admin.settings.roles')->name('admin.settings.roles');
    Volt::route('admin/settings/roles/create', 'admin.settings.role-form')->name('admin.settings.roles.create');
    Volt::route('admin/settings/roles/{role}/edit', 'admin.settings.role-form')->name('admin.settings.roles.edit');
    Volt::route('admin/settings/permissions', 'admin.settings.permissions')->name('admin.settings.permissions');
    Volt::route('admin/settings/security', 'admin.settings.security')->name('admin.settings.security');

    // Preferences
    Volt::route('admin/settings/preferences', 'admin.settings.preferences')->name('admin.settings.preferences');
    Volt::route('admin/settings/email', 'admin.settings.email')->name('admin.settings.email');
    Volt::route('admin/settings/email-templates', 'admin.settings.email-templates')->name('admin.settings.email-templates');
    Volt::route('admin/settings/email-templates/{template}/edit', 'admin.settings.email-template-form')->name('admin.settings.email-templates.edit');
    Volt::route('admin/settings/notifications', 'admin.settings.notifications')->name('admin.settings.notifications');
    Volt::route('admin/settings/scheduling', 'admin.settings.scheduling')->name('admin.settings.scheduling');

    // System
    Volt::route('admin/settings/action-log', 'admin.settings.action-log')->name('admin.settings.action-log');
    Volt::route('admin/settings/system-health', 'admin.settings.system-health')->name('admin.settings.system-health');
    Volt::route('admin/settings/infrastructure', 'admin.settings.infrastructure')->name('admin.settings.infrastructure');
    Volt::route('admin/settings/seeders', 'admin.settings.seeders')->name('admin.settings.seeders');
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
