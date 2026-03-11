<?php

use App\Http\Controllers\Api\V1\ActionLogController;
use App\Http\Controllers\Api\V1\CountryController;
use App\Http\Controllers\Api\V1\CustomFieldController;
use App\Http\Controllers\Api\V1\CustomFieldGroupController;
use App\Http\Controllers\Api\V1\ListNameController;
use App\Http\Controllers\Api\V1\ListValueController;
use App\Http\Controllers\Api\V1\MemberAddressController;
use App\Http\Controllers\Api\V1\MemberController;
use App\Http\Controllers\Api\V1\MemberEmailController;
use App\Http\Controllers\Api\V1\MemberLinkController;
use App\Http\Controllers\Api\V1\MemberPhoneController;
use App\Http\Controllers\Api\V1\MemberRelationshipController;
use App\Http\Controllers\Api\V1\OrganisationTaxClassController;
use App\Http\Controllers\Api\V1\ProductTaxClassController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\SystemController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All API routes are prefixed with /api automatically by Laravel.
| The v1 group adds the /v1 prefix and applies authentication +
| active-user middleware to all endpoints.
|
*/

Route::prefix('v1')->middleware([\App\Http\Middleware\ForceJsonResponse::class, 'throttle:api', 'auth:sanctum', 'signals.active-user'])->group(function (): void {

    // System
    Route::get('system/health', [SystemController::class, 'health'])->name('api.v1.system.health');

    // Settings
    Route::get('settings', [SettingsController::class, 'index'])->name('api.v1.settings.index');
    Route::get('settings/{group}', [SettingsController::class, 'show'])->name('api.v1.settings.show');
    Route::put('settings/{group}', [SettingsController::class, 'update'])->name('api.v1.settings.update');

    // Users
    Route::apiResource('users', UserController::class);

    // Roles
    Route::apiResource('roles', RoleController::class);

    // Action Log
    Route::get('actions', [ActionLogController::class, 'index'])->name('api.v1.actions.index');

    // Webhooks
    Route::apiResource('webhooks', WebhookController::class);
    Route::get('webhooks/{webhook}/logs', [WebhookController::class, 'logs'])->name('api.v1.webhooks.logs');

    // Members
    Route::apiResource('members', MemberController::class);
    Route::apiResource('members.addresses', MemberAddressController::class)->except(['show']);
    Route::apiResource('members.emails', MemberEmailController::class)->except(['show']);
    Route::apiResource('members.phones', MemberPhoneController::class)->except(['show']);
    Route::apiResource('members.links', MemberLinkController::class)->except(['show']);
    Route::apiResource('members.relationships', MemberRelationshipController::class)->only(['index', 'store', 'destroy']);

    // Countries (read-only)
    Route::apiResource('countries', CountryController::class)->only(['index', 'show']);

    // Custom Fields
    Route::apiResource('custom_field_groups', CustomFieldGroupController::class);
    Route::apiResource('custom_fields', CustomFieldController::class);

    // Lists of Values
    Route::apiResource('list_names', ListNameController::class);
    Route::apiResource('list_names.list_values', ListValueController::class)->except(['show']);

    // Tax Classes
    Route::apiResource('organisation_tax_classes', OrganisationTaxClassController::class);
    Route::apiResource('product_tax_classes', ProductTaxClassController::class);
});
