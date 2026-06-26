<?php

use App\Http\Controllers\Api\V1\AccessoryController;
use App\Http\Controllers\Api\V1\ActionLogController;
use App\Http\Controllers\Api\V1\ActivityController;
use App\Http\Controllers\Api\V1\AttachmentController;
use App\Http\Controllers\Api\V1\AvailabilityController;
use App\Http\Controllers\Api\V1\ContainerController;
use App\Http\Controllers\Api\V1\CountryController;
use App\Http\Controllers\Api\V1\CurrencyController;
use App\Http\Controllers\Api\V1\CustomFieldController;
use App\Http\Controllers\Api\V1\CustomFieldGroupController;
use App\Http\Controllers\Api\V1\CustomViewController;
use App\Http\Controllers\Api\V1\ExchangeRateController;
use App\Http\Controllers\Api\V1\ListNameController;
use App\Http\Controllers\Api\V1\ListValueController;
use App\Http\Controllers\Api\V1\MemberAddressController;
use App\Http\Controllers\Api\V1\MemberController;
use App\Http\Controllers\Api\V1\MemberEmailController;
use App\Http\Controllers\Api\V1\MemberLinkController;
use App\Http\Controllers\Api\V1\MemberPhoneController;
use App\Http\Controllers\Api\V1\MemberRelationshipController;
use App\Http\Controllers\Api\V1\OpportunityController;
use App\Http\Controllers\Api\V1\OpportunityVersionController;
use App\Http\Controllers\Api\V1\OrganisationTaxClassController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductGroupController;
use App\Http\Controllers\Api\V1\ProductRateController;
use App\Http\Controllers\Api\V1\ProductTaxClassController;
use App\Http\Controllers\Api\V1\RateCalculationController;
use App\Http\Controllers\Api\V1\RateDefinitionController;
use App\Http\Controllers\Api\V1\RateEngineMetaController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SchemaController;
use App\Http\Controllers\Api\V1\SerialisedComponentController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\ShortageController;
use App\Http\Controllers\Api\V1\StockLevelController;
use App\Http\Controllers\Api\V1\StockTransactionController;
use App\Http\Controllers\Api\V1\SystemController;
use App\Http\Controllers\Api\V1\TaxRateController;
use App\Http\Controllers\Api\V1\TaxRuleController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\WebhookController;
use App\Http\Middleware\ForceJsonResponse;
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

Route::prefix('v1')->middleware([ForceJsonResponse::class, 'throttle:api', 'auth:sanctum', 'signals.active-user'])->group(function (): void {

    // System
    Route::get('system/health', [SystemController::class, 'health'])->name('api.v1.system.health');

    // Schema Discovery
    Route::get('schema', [SchemaController::class, 'index'])->name('api.v1.schema.index');
    Route::get('schema/{model}', [SchemaController::class, 'show'])->name('api.v1.schema.show');

    // Settings
    Route::get('settings', [SettingsController::class, 'index'])->name('api.v1.settings.index');
    Route::get('settings/{group}', [SettingsController::class, 'show'])->name('api.v1.settings.show');
    Route::put('settings/{group}', [SettingsController::class, 'update'])->name('api.v1.settings.update');

    // Users
    Route::apiResource('users', UserController::class)->names('api.v1.users');

    // Roles
    Route::apiResource('roles', RoleController::class)->names('api.v1.roles');

    // Action Log
    Route::get('actions', [ActionLogController::class, 'index'])->name('api.v1.actions.index');
    Route::post('actions/export', [ActionLogController::class, 'export'])->name('api.v1.actions.export');

    // Webhooks
    Route::apiResource('webhooks', WebhookController::class)->names('api.v1.webhooks');
    Route::get('webhooks/{webhook}/logs', [WebhookController::class, 'logs'])->name('api.v1.webhooks.logs');

    // Members
    Route::apiResource('members', MemberController::class)->names('api.v1.members');
    Route::post('members/{member}/merge', [MemberController::class, 'merge'])->name('api.v1.members.merge');
    Route::post('members/{member}/anonymise', [MemberController::class, 'anonymise'])->name('api.v1.members.anonymise');
    Route::post('members/{member}/restore', [MemberController::class, 'restore'])
        ->withTrashed()
        ->name('api.v1.members.restore');
    Route::apiResource('members.addresses', MemberAddressController::class)->except(['show'])->names('api.v1.members.addresses');
    Route::apiResource('members.emails', MemberEmailController::class)->except(['show'])->names('api.v1.members.emails');
    Route::apiResource('members.phones', MemberPhoneController::class)->except(['show'])->names('api.v1.members.phones');
    Route::apiResource('members.links', MemberLinkController::class)->except(['show'])->names('api.v1.members.links');
    Route::apiResource('members.relationships', MemberRelationshipController::class)->only(['index', 'store', 'destroy'])->names('api.v1.members.relationships');
    Route::get('members/{member}/attachments', [AttachmentController::class, 'indexForMember'])->name('api.v1.members.attachments.index');

    // Custom Views
    Route::apiResource('custom_views', CustomViewController::class)->names('api.v1.custom_views');
    Route::post('custom_views/{custom_view}/clone', [CustomViewController::class, 'clone'])->name('api.v1.custom_views.clone');

    // Countries (read-only)
    Route::apiResource('countries', CountryController::class)->only(['index', 'show'])->names('api.v1.countries');

    // Custom Fields
    Route::apiResource('custom_field_groups', CustomFieldGroupController::class)->names('api.v1.custom_field_groups');
    Route::apiResource('custom_fields', CustomFieldController::class)->names('api.v1.custom_fields');

    // Lists of Values
    Route::apiResource('list_names', ListNameController::class)->names('api.v1.list_names');
    Route::apiResource('list_names.list_values', ListValueController::class)->except(['show'])->names('api.v1.list_names.list_values');

    // RMS-compatible alias for Lists of Values
    Route::apiResource('list_of_values', ListNameController::class)->parameters(['list_of_values' => 'list_name'])->names('api.v1.list_of_values');
    Route::apiResource('list_of_values.list_values', ListValueController::class)->parameters(['list_of_values' => 'list_name', 'list_values' => 'list_value'])->except(['show'])->names('api.v1.list_of_values.list_values');

    // Tax Classes
    Route::apiResource('organisation_tax_classes', OrganisationTaxClassController::class)->names('api.v1.organisation_tax_classes');
    Route::apiResource('product_tax_classes', ProductTaxClassController::class)->names('api.v1.product_tax_classes');

    // Tax Rates & Rules
    Route::apiResource('tax_rates', TaxRateController::class)->names('api.v1.tax_rates');
    Route::apiResource('tax_rules', TaxRuleController::class)->names('api.v1.tax_rules');

    // Currencies (read-only)
    Route::apiResource('currencies', CurrencyController::class)->only(['index', 'show'])->names('api.v1.currencies');

    // Exchange Rates
    Route::apiResource('exchange_rates', ExchangeRateController::class)->names('api.v1.exchange_rates');

    // Attachments
    Route::apiResource('attachments', AttachmentController::class)->only(['show', 'store', 'destroy'])->names('api.v1.attachments');

    // Products
    Route::apiResource('products', ProductController::class)->names('api.v1.products');
    Route::post('products/{product}/merge', [ProductController::class, 'merge'])->name('api.v1.products.merge');
    Route::apiResource('products.accessories', AccessoryController::class)->only(['index', 'store', 'update', 'destroy'])->names('api.v1.products.accessories');
    Route::apiResource('products.rates', ProductRateController::class)->names('api.v1.products.rates');
    Route::post('products/{product}/calculate_rate', [RateCalculationController::class, 'calculate'])->name('api.v1.products.calculate_rate');
    // Kit composition (serialised_components — the product's bill-of-materials)
    Route::apiResource('products.components', SerialisedComponentController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->parameters(['components' => 'component'])
        ->names('api.v1.products.components');

    // Product Groups
    Route::apiResource('product_groups', ProductGroupController::class)->names('api.v1.product_groups');

    // Stock Levels
    Route::apiResource('stock_levels', StockLevelController::class)->names('api.v1.stock_levels');

    // Containers (M5-3b availability subset — read + pack/unpack only; the full
    // seal/dissolve/scan/dispatch/return lifecycle is Phase-4). The pack/unpack
    // sub-actions are declared before the resource routes so the explicit verbs win.
    Route::post('containers/{container}/pack', [ContainerController::class, 'pack'])->name('api.v1.containers.pack');
    Route::post('containers/{container}/unpack', [ContainerController::class, 'unpack'])->name('api.v1.containers.unpack');
    Route::get('containers', [ContainerController::class, 'index'])->name('api.v1.containers.index');
    Route::get('containers/{container}', [ContainerController::class, 'show'])->name('api.v1.containers.show');

    // Availability (read-only: point query via ?date, range query via ?from&?to).
    // The static calendar/shortages grids and the {product}/gantt bar view are
    // declared before the flat index so the literal segments win the route match.
    Route::get('availability/calendar', [AvailabilityController::class, 'calendar'])->name('api.v1.availability.calendar');
    Route::get('availability/shortages', [AvailabilityController::class, 'shortages'])->name('api.v1.availability.shortages');
    Route::get('availability/{product}/gantt', [AvailabilityController::class, 'gantt'])->name('api.v1.availability.gantt');
    Route::get('availability', [AvailabilityController::class, 'index'])->name('api.v1.availability.index');
    Route::get('products/{product}/availability', [AvailabilityController::class, 'showForProduct'])->name('api.v1.products.availability');
    // Serialised assets of a product free across ?from&?to at ?store_id.
    Route::get('products/{product}/available-assets', [AvailabilityController::class, 'availableAssets'])->name('api.v1.products.available_assets');

    // Stock Transactions (nested under products/stock_levels, matching RMS)
    Route::get('products/{product}/stock_levels/{stock_level}/stock_transactions', [StockTransactionController::class, 'index'])->name('api.v1.stock_transactions.index');
    Route::get('products/{product}/stock_levels/{stock_level}/stock_transactions/{stock_transaction}', [StockTransactionController::class, 'show'])->name('api.v1.stock_transactions.show');
    Route::post('products/{product}/stock_levels/{stock_level}/stock_transactions', [StockTransactionController::class, 'store'])->name('api.v1.stock_transactions.store');
    Route::delete('products/{product}/stock_levels/{stock_level}/stock_transactions/{stock_transaction}', [StockTransactionController::class, 'destroy'])->name('api.v1.stock_transactions.destroy');

    // Activities
    Route::apiResource('activities', ActivityController::class)->names('api.v1.activities');
    Route::post('activities/{activity}/complete', [ActivityController::class, 'complete'])->name('api.v1.activities.complete');

    // Opportunities (event-sourced lifecycle)
    // Read sub-resources (assets / availability / activity) — declared before the
    // apiResource so the explicit GET routes win.
    Route::get('opportunities/{opportunity}/assets', [OpportunityController::class, 'assets'])->name('api.v1.opportunities.assets');
    Route::get('opportunities/{opportunity}/availability', [OpportunityController::class, 'availability'])->name('api.v1.opportunities.availability');
    Route::get('opportunities/{opportunity}/activity', [OpportunityController::class, 'activity'])->name('api.v1.opportunities.activity');
    // Guard-aware action availability for the Show toolbar (dry-run, no side effects).
    Route::get('opportunities/{opportunity}/available_actions', [OpportunityController::class, 'availableActions'])->name('api.v1.opportunities.available_actions');
    Route::post('opportunities/{opportunity}/clone', [OpportunityController::class, 'clone'])->name('api.v1.opportunities.clone');
    Route::post('opportunities/{opportunity}/convert_to_quotation', [OpportunityController::class, 'convertToQuotation'])->name('api.v1.opportunities.convert_to_quotation');
    Route::post('opportunities/{opportunity}/convert_to_order', [OpportunityController::class, 'convertToOrder'])->name('api.v1.opportunities.convert_to_order');
    // Backward-transition lifecycle moves (routed through the guard pipeline).
    Route::post('opportunities/{opportunity}/reinstate', [OpportunityController::class, 'reinstate'])->name('api.v1.opportunities.reinstate');
    Route::post('opportunities/{opportunity}/revert_to_quotation', [OpportunityController::class, 'revertToQuotation'])->name('api.v1.opportunities.revert_to_quotation');
    Route::post('opportunities/{opportunity}/revert_to_draft', [OpportunityController::class, 'revertToDraft'])->name('api.v1.opportunities.revert_to_draft');
    Route::post('opportunities/{opportunity}/reopen', [OpportunityController::class, 'reopen'])->name('api.v1.opportunities.reopen');
    // Restore (un-archive) a soft-deleted opportunity — the binding resolves the
    // trashed projection row (mirrors members/{member}/restore).
    Route::post('opportunities/{opportunity}/restore', [OpportunityController::class, 'restore'])
        ->withTrashed()
        ->name('api.v1.opportunities.restore');
    Route::post('opportunities/{opportunity}/lock_locks', [OpportunityController::class, 'lockLocks'])->name('api.v1.opportunities.lock_locks');
    Route::post('opportunities/{opportunity}/unlock_locks', [OpportunityController::class, 'unlockLocks'])->name('api.v1.opportunities.unlock_locks');
    Route::post('opportunities/{opportunity}/change_status', [OpportunityController::class, 'changeStatus'])->name('api.v1.opportunities.change_status');
    // Line items (priced via the rate + tax engines; totals roll up to the parent)
    Route::post('opportunities/{opportunity}/items', [OpportunityController::class, 'storeItem'])->name('api.v1.opportunities.items.store');
    Route::patch('opportunities/{opportunity}/items/tree', [OpportunityController::class, 'restructureItemsTree'])->name('api.v1.opportunities.items.tree');
    // Per-asset allocation sub-resource (M5). Declared before items/{item} PATCH so
    // the explicit asset routes win.
    Route::post('opportunities/{opportunity}/items/{item}/assets', [OpportunityController::class, 'storeAsset'])->name('api.v1.opportunities.items.assets.store');
    Route::patch('opportunities/{opportunity}/items/{item}/assets/{asset}', [OpportunityController::class, 'updateAsset'])->name('api.v1.opportunities.items.assets.update');
    Route::delete('opportunities/{opportunity}/items/{item}/assets/{asset}', [OpportunityController::class, 'destroyAsset'])->name('api.v1.opportunities.items.assets.destroy');
    // Bulk-line dispatch/return/adjust (M5-2 — non-serialised lines). Declared
    // before items/{item} PATCH so the explicit fulfilment route wins.
    Route::patch('opportunities/{opportunity}/items/{item}/fulfilment', [OpportunityController::class, 'updateBulkQuantity'])->name('api.v1.opportunities.items.fulfilment');
    Route::patch('opportunities/{opportunity}/items/{item}', [OpportunityController::class, 'updateItem'])->name('api.v1.opportunities.items.update');
    Route::delete('opportunities/{opportunity}/items/{item}', [OpportunityController::class, 'destroyItem'])->name('api.v1.opportunities.items.destroy');
    // Batch asset allocation (RMS quick_allocate).
    Route::post('opportunities/{opportunity}/quick_allocate', [OpportunityController::class, 'quickAllocate'])->name('api.v1.opportunities.quick_allocate');
    // Batch asset preparation (RMS quick_prepare).
    Route::post('opportunities/{opportunity}/quick_prepare', [OpportunityController::class, 'quickPrepare'])->name('api.v1.opportunities.quick_prepare');
    // Batch dispatch/return (RMS quick_book_out / quick_check_in).
    Route::post('opportunities/{opportunity}/quick_book_out', [OpportunityController::class, 'quickBookOut'])->name('api.v1.opportunities.quick_book_out');
    Route::post('opportunities/{opportunity}/quick_check_in', [OpportunityController::class, 'quickCheckIn'])->name('api.v1.opportunities.quick_check_in');
    // Ad-hoc costs (taxed, not rate-priced; totals roll up to the parent)
    Route::post('opportunities/{opportunity}/costs', [OpportunityController::class, 'storeCost'])->name('api.v1.opportunities.costs.store');
    Route::patch('opportunities/{opportunity}/costs/{cost}', [OpportunityController::class, 'updateCost'])->name('api.v1.opportunities.costs.update');
    Route::delete('opportunities/{opportunity}/costs/{cost}', [OpportunityController::class, 'destroyCost'])->name('api.v1.opportunities.costs.destroy');
    // Participants (members in a named role; plain CRM associations, RMS participants[])
    Route::post('opportunities/{opportunity}/participants', [OpportunityController::class, 'storeParticipant'])->name('api.v1.opportunities.participants.store');
    Route::patch('opportunities/{opportunity}/participants/{participant}', [OpportunityController::class, 'updateParticipant'])->name('api.v1.opportunities.participants.update');
    Route::delete('opportunities/{opportunity}/participants/{participant}', [OpportunityController::class, 'destroyParticipant'])->name('api.v1.opportunities.participants.destroy');
    // Manual deal-total override
    Route::post('opportunities/{opportunity}/deal_price', [OpportunityController::class, 'setDealPrice'])->name('api.v1.opportunities.deal_price.set');
    Route::delete('opportunities/{opportunity}/deal_price', [OpportunityController::class, 'clearDealPrice'])->name('api.v1.opportunities.deal_price.clear');
    // Shortages (computed detection + non-PO resolution)
    Route::get('opportunities/{opportunity}/shortages', [ShortageController::class, 'index'])->name('api.v1.opportunities.shortages.index');
    // Persisted resolutions recorded against the opportunity (panel "active
    // resolutions" tab) — paginated + Ransack-filterable by status.
    Route::get('opportunities/{opportunity}/shortage_resolutions', [ShortageController::class, 'resolutions'])->name('api.v1.opportunities.shortage_resolutions.index');
    // Read-only confirmation-gate pre-check (Block/Warn/Allow) for the convert
    // dialog — NO acknowledgement recorded, NO auto-resolution.
    Route::get('opportunities/{opportunity}/shortage_gate', [ShortageController::class, 'gate'])->name('api.v1.opportunities.shortage_gate');
    Route::get('opportunities/{opportunity}/items/{item}/shortage_resolvers', [ShortageController::class, 'resolvers'])->name('api.v1.opportunities.shortage_resolvers');
    Route::post('opportunities/{opportunity}/shortages/acknowledge', [ShortageController::class, 'acknowledge'])->name('api.v1.opportunities.shortages.acknowledge');
    Route::post('shortage_resolutions', [ShortageController::class, 'resolve'])->name('api.v1.shortage_resolutions.store');
    // Resolution status-transition lifecycle (§8.3): progress/cancel/fail a
    // recorded resolution. Each transition validates the §8.3 matrix (422 on an
    // illegal move) and emits the matching `shortage.resolution.*` event.
    Route::patch('shortage_resolutions/{resolution}/confirm', [ShortageController::class, 'confirmResolution'])->name('api.v1.shortage_resolutions.confirm');
    Route::patch('shortage_resolutions/{resolution}/start', [ShortageController::class, 'startResolution'])->name('api.v1.shortage_resolutions.start');
    Route::patch('shortage_resolutions/{resolution}/fulfill', [ShortageController::class, 'fulfillResolution'])->name('api.v1.shortage_resolutions.fulfill');
    Route::patch('shortage_resolutions/{resolution}/cancel', [ShortageController::class, 'cancelResolution'])->name('api.v1.shortage_resolutions.cancel');
    Route::patch('shortage_resolutions/{resolution}/fail', [ShortageController::class, 'failResolution'])->name('api.v1.shortage_resolutions.fail');

    // Quote versions (sub-resource — revisions + alternatives). Declared before the
    // opportunities apiResource so the explicit routes win. The diff route is
    // declared before `versions/{version}` so {version} does not swallow `diff`.
    Route::get('opportunities/{opportunity}/versions', [OpportunityVersionController::class, 'index'])->name('api.v1.opportunities.versions.index');
    Route::post('opportunities/{opportunity}/versions', [OpportunityVersionController::class, 'store'])->name('api.v1.opportunities.versions.store');
    Route::get('opportunities/{opportunity}/versions/{from}/diff/{to}', [OpportunityVersionController::class, 'diff'])->name('api.v1.opportunities.versions.diff');
    Route::post('opportunities/{opportunity}/versions/{version}/activate', [OpportunityVersionController::class, 'activate'])->name('api.v1.opportunities.versions.activate');
    Route::post('opportunities/{opportunity}/versions/{version}/send', [OpportunityVersionController::class, 'send'])->name('api.v1.opportunities.versions.send');
    Route::post('opportunities/{opportunity}/versions/{version}/accept', [OpportunityVersionController::class, 'accept'])->name('api.v1.opportunities.versions.accept');
    Route::post('opportunities/{opportunity}/versions/{version}/decline', [OpportunityVersionController::class, 'decline'])->name('api.v1.opportunities.versions.decline');
    Route::get('opportunities/{opportunity}/versions/{version}', [OpportunityVersionController::class, 'show'])->name('api.v1.opportunities.versions.show');
    Route::patch('opportunities/{opportunity}/versions/{version}', [OpportunityVersionController::class, 'update'])->name('api.v1.opportunities.versions.update');
    Route::delete('opportunities/{opportunity}/versions/{version}', [OpportunityVersionController::class, 'destroy'])->name('api.v1.opportunities.versions.destroy');

    Route::apiResource('opportunities', OpportunityController::class)->names('api.v1.opportunities');

    // Rate Definitions
    Route::post('rate_definitions/{rate_definition}/duplicate', [RateDefinitionController::class, 'duplicate'])->name('api.v1.rate_definitions.duplicate');
    Route::apiResource('rate_definitions', RateDefinitionController::class)->names('api.v1.rate_definitions');

    // Rate Engine metadata (for external form builders)
    Route::get('rate_engine/strategies', [RateEngineMetaController::class, 'strategies'])->name('api.v1.rate_engine.strategies');
    Route::get('rate_engine/modifiers', [RateEngineMetaController::class, 'modifiers'])->name('api.v1.rate_engine.modifiers');
    Route::get('rate_engine/presets', [RateEngineMetaController::class, 'presets'])->name('api.v1.rate_engine.presets');
    Route::get('rate_engine/schema', [RateEngineMetaController::class, 'schema'])->name('api.v1.rate_engine.schema');
});
