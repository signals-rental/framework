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
use App\Enums\ShortageResolutionType;
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
        'item_id' => $product->id,
        'item_type' => Product::class,
        'quantity' => '3',
    ]));

    (new ConvertToQuotation)($opportunity->fresh());

    return $opportunity->fresh();
}

it('auto-applies the partial resolver when auto-resolve is on, leaving a residual gate', function () {
    $opportunity = autoResolveQuotation([
        'shortage_policy' => ShortagePolicy::Allow->value,
        'shortage_auto_resolve_enabled' => true,
        'shortage_preferred_resolvers' => ['partial'],
    ]);

    $count = app(ShortageAutoResolver::class)->resolve($opportunity);

    expect($count)->toBe(1);

    // Partial fulfilment recorded a Confirmed resolution covering the 1 available
    // unit (5 held - 4 committed = 1 free).
    $resolution = ShortageResolution::query()->first();
    expect($resolution)->not->toBeNull()
        ->and($resolution->resolution_type)->toBe(ShortageResolutionType::Partial)
        ->and($resolution->quantity_resolved)->toBe(1);
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

it('falls back to all resolvers in priority order when none configured', function () {
    $opportunity = autoResolveQuotation([
        'shortage_policy' => ShortagePolicy::Allow->value,
        'shortage_auto_resolve_enabled' => true,
        'shortage_preferred_resolvers' => null,
    ]);

    $count = app(ShortageAutoResolver::class)->resolve($opportunity);

    // Partial is the only auto-executable built-in that produces an option here,
    // so exactly one execution happens even without an explicit order.
    expect($count)->toBe(1)
        ->and(ShortageResolution::query()->where('resolution_type', ShortageResolutionType::Partial->value)->count())->toBe(1);
});

it('runs inside ConvertToOrder so the gate sees the residual before blocking', function () {
    // Block policy, auto-resolve on with partial. Partial covers the 1 available
    // unit but 1 still short, so a Block policy still blocks on the residual.
    $opportunity = autoResolveQuotation([
        'shortage_policy' => ShortagePolicy::Block->value,
        'shortage_auto_resolve_enabled' => true,
        'shortage_preferred_resolvers' => ['partial'],
    ]);

    expect(fn () => (new ConvertToOrder)($opportunity))
        ->toThrow(ValidationException::class);

    // Even though the conversion rolled back, the auto-resolution ran within the
    // same transaction and was rolled back too — so no orphan resolution remains.
    expect($opportunity->fresh()->state)->toBe(OpportunityState::Quotation)
        ->and(ShortageResolution::query()->count())->toBe(0);
});
