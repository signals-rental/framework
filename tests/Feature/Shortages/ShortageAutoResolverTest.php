<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\AvailabilityResolution;
use App\Enums\DemandPhase;
use App\Enums\OpportunityState;
use App\Enums\ShortagePolicy;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ShortageResolution;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Services\Shortages\ShortageAutoResolver;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->app->bind(AvailabilityResolutionProvider::class, fn () => new class implements AvailabilityResolutionProvider
    {
        public function resolve(): AvailabilityResolution
        {
            return AvailabilityResolution::Daily;
        }
    });

    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('Sales');
    $this->actingAs($user);
});

/**
 * A short quotation: 5 held, 4 committed elsewhere, line wants 3 (short by 2).
 * The store is created with the supplied configuration.
 *
 * @param  array<string, mixed>  $storeConfig
 */
function autoResolveQuotation(array $storeConfig): Opportunity
{
    $store = Store::factory()->create(['timezone' => 'UTC'] + $storeConfig);

    $product = Product::factory()->rental()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity_held' => 5,
    ]);

    Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window(Carbon::parse('2026-07-01T09:00:00Z'), Carbon::parse('2026-07-05T17:00:00Z'))
        ->create([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'quantity' => 4,
            'source_type' => 'opportunity_item',
            'source_id' => 999001,
            'metadata' => [],
        ]);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Auto-resolve test',
        'store_id' => $store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-05T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $product->name,
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'quantity' => '3',
    ]));

    (new ConvertToQuotation)($opportunity->fresh());

    return $opportunity->fresh();
}

it('does not auto-apply the partial resolver — partial requires business judgement (spec §4.5)', function () {
    // Even with partial preferred and auto-resolve on, the auto-resolver must NOT
    // silently reduce the line quantity: PartialFulfilmentResolver is
    // "Auto-executable: No" per spec §4.5. Auto-resolution finds no
    // auto-executable option and records nothing.
    $opportunity = autoResolveQuotation([
        'shortage_policy' => ShortagePolicy::Allow->value,
        'shortage_auto_resolve_enabled' => true,
        'shortage_preferred_resolvers' => ['partial'],
    ]);

    $count = app(ShortageAutoResolver::class)->resolve($opportunity);

    expect($count)->toBe(0)
        ->and(ShortageResolution::query()->count())->toBe(0);
});

it('is a no-op when auto-resolve is off', function () {
    $opportunity = autoResolveQuotation([
        'shortage_policy' => ShortagePolicy::Allow->value,
        'shortage_auto_resolve_enabled' => false,
    ]);

    $count = app(ShortageAutoResolver::class)->resolve($opportunity);

    expect($count)->toBe(0)
        ->and(ShortageResolution::query()->count())->toBe(0);
});

it('records nothing when none configured because no built-in resolver is auto-executable', function () {
    // With no preferred order the auto-resolver scans every registered resolver in
    // priority order — but none of the built-ins is auto-executable per spec, so
    // it auto-applies nothing and records no resolution.
    $opportunity = autoResolveQuotation([
        'shortage_policy' => ShortagePolicy::Allow->value,
        'shortage_auto_resolve_enabled' => true,
        'shortage_preferred_resolvers' => null,
    ]);

    $count = app(ShortageAutoResolver::class)->resolve($opportunity);

    expect($count)->toBe(0)
        ->and(ShortageResolution::query()->count())->toBe(0);
});

it('runs inside ConvertToOrder and a Block policy still blocks the unresolved shortage', function () {
    // Block policy, auto-resolve on with partial preferred. Partial is NOT
    // auto-executable, so nothing is auto-applied and the full shortage remains —
    // a Block policy blocks the conversion.
    $opportunity = autoResolveQuotation([
        'shortage_policy' => ShortagePolicy::Block->value,
        'shortage_auto_resolve_enabled' => true,
        'shortage_preferred_resolvers' => ['partial'],
    ]);

    expect(fn () => (new ConvertToOrder)($opportunity))
        ->toThrow(ValidationException::class);

    // The conversion rolled back and no resolution was auto-recorded.
    expect($opportunity->fresh()->state)->toBe(OpportunityState::Quotation)
        ->and(ShortageResolution::query()->count())->toBe(0);
});
