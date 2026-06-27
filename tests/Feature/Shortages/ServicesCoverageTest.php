<?php

use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Contracts\ShortageResolverContract;
use App\Enums\AvailabilityResolution;
use App\Enums\ShortageDispatchPolicy;
use App\Enums\ShortageResolutionStatus;
use App\Enums\ShortageResolutionType;
use App\Enums\StockMethod;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\ShortageResolution;
use App\Models\ShortageWaitlistMonitor;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Services\Shortages\DispatchShortageGate;
use App\Services\Shortages\Resolvers\AbstractShortageResolver;
use App\Services\Shortages\Resolvers\PartialFulfilmentResolver;
use App\Services\Shortages\Resolvers\QuoteReallocationResolver;
use App\Services\Shortages\Resolvers\SubstitutionResolver;
use App\Services\Shortages\Resolvers\WaitlistResolver;
use App\Services\Shortages\ShortageAutoResolver;
use App\Services\Shortages\ShortageDetector;
use App\Services\Shortages\ShortageResolverDefinition;
use App\Services\Shortages\ShortageResolverRegistry;
use App\ValueObjects\ResolutionOption;
use App\ValueObjects\ResolutionResult;
use App\ValueObjects\Shortage;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;

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
    $this->actingAs(User::factory()->owner()->create());
});

/**
 * A bulk shortage VO backed by real product/store/item rows.
 */
function coverageShortage(int $shortfall = 2, int $available = 1): Shortage
{
    $store = Store::query()->first() ?? Store::factory()->create();
    $product = Product::factory()->rental()->bulk()->create();
    $item = OpportunityItem::factory()->create([
        'item_type' => 'product',
        'itemable_id' => $product->id,
    ]);

    return Shortage::make(
        opportunityItemId: $item->id,
        opportunityId: $item->opportunity_id,
        productId: $product->id,
        productName: $product->name,
        storeId: $store->id,
        requestedQuantity: $available + $shortfall,
        availableQuantity: $available,
        trackingType: StockMethod::Bulk,
        startsAt: Carbon::parse('2026-07-01T09:00:00Z'),
        endsAt: Carbon::parse('2026-07-05T17:00:00Z'),
        isCritical: false,
    );
}

/*
|--------------------------------------------------------------------------
| Resolver name() + early-return branches
|--------------------------------------------------------------------------
*/

it('exposes the human-readable name of every built-in resolver', function () {
    expect(app(PartialFulfilmentResolver::class)->name())->toBe('Partial fulfilment')
        ->and(app(QuoteReallocationResolver::class)->name())->toBe('Reallocate from quote')
        ->and(app(SubstitutionResolver::class)->name())->toBe('Substitute product')
        ->and(app(WaitlistResolver::class)->name())->toBe('Waitlist');
});

it('returns no partial-fulfilment option when none of the requested quantity is available', function () {
    // availableQuantity = 0 → canResolve() is false → getOptions() takes the empty
    // early-return branch (offering "fulfil with zero" is meaningless).
    $shortage = coverageShortage(shortfall: 2, available: 0);
    $resolver = app(PartialFulfilmentResolver::class);

    expect($resolver->canResolve($shortage))->toBeFalse()
        ->and($resolver->getOptions($shortage))->toBe([]);
});

it('returns no waitlist option when the shortage is fully resolved (no remaining shortfall)', function () {
    // remainingShortfall() = 0 → canResolve() false → getOptions() empty branch.
    $shortage = coverageShortage(shortfall: 0, available: 3);
    $resolver = app(WaitlistResolver::class);

    expect($shortage->remainingShortfall())->toBe(0)
        ->and($resolver->canResolve($shortage))->toBeFalse()
        ->and($resolver->getOptions($shortage))->toBe([]);
});

it('clamps an open-ended waitlist monitor to the default 30-day expiry horizon', function () {
    // An effectively indefinite end date (the sentinel, far in the future) triggers
    // the default-horizon clamp instead of expiring at the window end.
    $store = Store::factory()->create();
    $product = Product::factory()->rental()->bulk()->create();
    $item = OpportunityItem::factory()->create([
        'item_type' => 'product',
        'itemable_id' => $product->id,
    ]);

    $shortage = Shortage::make(
        opportunityItemId: $item->id,
        opportunityId: $item->opportunity_id,
        productId: $product->id,
        productName: $product->name,
        storeId: $store->id,
        requestedQuantity: 3,
        availableQuantity: 1,
        trackingType: StockMethod::Bulk,
        startsAt: Carbon::parse('2026-07-01T09:00:00Z'),
        endsAt: Demand::sentinel(),
        isCritical: false,
    );

    $resolver = app(WaitlistResolver::class);
    $result = $resolver->apply($shortage, $resolver->getOptions($shortage)[0]);

    expect($result->status)->toBe(ShortageResolutionStatus::Monitoring);

    $monitor = ShortageWaitlistMonitor::query()
        ->where('shortage_resolution_id', $result->resolution->id)
        ->firstOrFail();

    // Clamped to ~30 days out, NOT the multi-millennium sentinel.
    expect($monitor->expires_at->lessThan(now()->addYears(1)))->toBeTrue()
        ->and($monitor->expires_at->greaterThan(now()->addDays(28)))->toBeTrue();
});

it('exposes the registered resolver definitions keyed by resolver key', function () {
    $definitions = app(ShortageResolverRegistry::class)->definitions();

    expect($definitions)->toBeArray()
        ->and($definitions)->toHaveKey('partial')
        ->and($definitions['partial'])->toBeInstanceOf(ShortageResolverDefinition::class)
        ->and($definitions['waitlist']->key)->toBe('waitlist');
});

/*
|--------------------------------------------------------------------------
| DispatchShortageGate::resolvePolicy default fallback (line 160)
|--------------------------------------------------------------------------
*/

it('returns the default dispatch policy when no items are supplied to the gate', function () {
    // An empty item batch never enters the resolvePolicy loop, falling through to
    // the default-policy return. No shortages either, so the result is not short.
    $gate = app(DispatchShortageGate::class);

    $result = $gate->enforceForItems([]);

    expect($result->isShort())->toBeFalse()
        ->and($result->blocks())->toBeFalse()
        ->and($result->policy)->toBe(ShortageDispatchPolicy::default());
});

/*
|--------------------------------------------------------------------------
| ShortageDetector early returns (lines 85, 190)
|--------------------------------------------------------------------------
*/

it('returns no shortage for a product line with a non-positive requested quantity', function () {
    $store = Store::factory()->create(['timezone' => 'UTC']);
    $product = Product::factory()->rental()->bulk()->create(['track_availability' => true]);
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity_held' => 5,
    ]);

    $opportunity = Opportunity::factory()->create([
        'store_id' => $store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-05T17:00:00Z',
    ]);
    // Zero quantity, no allocated assets → requestedQuantity() is 0 → null shortage.
    $item = OpportunityItem::factory()->create([
        'opportunity_id' => $opportunity->id,
        'item_type' => 'product',
        'itemable_type' => Product::class,
        'itemable_id' => $product->id,
        'quantity' => 0,
    ]);

    expect(app(ShortageDetector::class)->forItem($item, $opportunity))->toBeNull();
});

it('reads the pre-loaded product relation instead of re-querying when detecting', function () {
    $store = Store::factory()->create(['timezone' => 'UTC']);
    $product = Product::factory()->rental()->bulk()->create(['track_availability' => true]);
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity_held' => 2,
    ]);

    $opportunity = Opportunity::factory()->create([
        'store_id' => $store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-05T17:00:00Z',
    ]);
    $item = OpportunityItem::factory()->create([
        'opportunity_id' => $opportunity->id,
        'item_type' => 'product',
        'itemable_type' => Product::class,
        'itemable_id' => $product->id,
        'quantity' => 5,
    ]);

    // Pre-set the polymorphic `item` relation to the Product instance, mirroring
    // what forOpportunity's loadMissing('items.item') achieves — so resolveProduct
    // takes the relationLoaded() branch and returns the loaded model directly
    // rather than issuing a Product::find().
    $item->setRelation('item', $product);

    expect($item->relationLoaded('item'))->toBeTrue();

    $shortage = app(ShortageDetector::class)->forItem($item, $opportunity);

    expect($shortage)->not->toBeNull()
        ->and($shortage->productId)->toBe($product->id)
        ->and($shortage->availableQuantity)->toBe(2)
        ->and($shortage->shortfall)->toBe(3);
});

/*
|--------------------------------------------------------------------------
| ShortageAutoResolver branches (lines 80, 91, 121)
|--------------------------------------------------------------------------
*/

/**
 * Auto-executable resolver that offers NO auto-executable option (its single
 * option is flagged autoExecutable: false). Exercises the autoExecutableOption()
 * null return (121) and the resolveItem() continue (91).
 */
class NoAutoOptionResolver extends AbstractShortageResolver implements ShortageResolverContract
{
    public function key(): string
    {
        return 'no_auto_option';
    }

    public function name(): string
    {
        return 'No auto option';
    }

    public function priority(): int
    {
        return 1;
    }

    public function isAutoExecutable(): bool
    {
        return true;
    }

    public function canResolve(Shortage $shortage): bool
    {
        return $shortage->remainingShortfall() > 0;
    }

    public function getOptions(Shortage $shortage): array
    {
        return [
            new ResolutionOption(
                resolverKey: $this->key(),
                type: ShortageResolutionType::Partial,
                label: 'Needs confirmation',
                description: 'Not auto-executable',
                quantityResolved: $shortage->remainingShortfall(),
                isPartial: false,
                autoExecutable: false,
            ),
        ];
    }

    public function apply(Shortage $shortage, ResolutionOption $option): ResolutionResult
    {
        $resolution = $this->record(
            shortage: $shortage,
            quantityResolved: $option->quantityResolved,
            status: ShortageResolutionStatus::Confirmed,
        );

        return ResolutionResult::confirmed($resolution, 'Applied in test.');
    }

    protected function resolutionType(): ShortageResolutionType
    {
        return ShortageResolutionType::Partial;
    }
}

/**
 * Build a short, auto-resolve-enabled order whose store prefers $resolverKey.
 */
function autoResolveOpportunity(string $resolverKey): Opportunity
{
    $store = Store::factory()->create([
        'timezone' => 'UTC',
        'shortage_auto_resolve_enabled' => true,
        'shortage_preferred_resolvers' => [$resolverKey],
    ]);
    $product = Product::factory()->rental()->bulk()->create(['track_availability' => true]);
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity_held' => 2,
    ]);

    $opportunity = Opportunity::factory()->create([
        'store_id' => $store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-05T17:00:00Z',
    ]);
    OpportunityItem::factory()->create([
        'opportunity_id' => $opportunity->id,
        'item_type' => 'product',
        'itemable_type' => Product::class,
        'itemable_id' => $product->id,
        'quantity' => 5,
    ]);

    return $opportunity->fresh();
}

it('skips an auto-executable resolver that offers no auto-executable option', function () {
    app()->bind(NoAutoOptionResolver::class);
    app(ShortageResolverRegistry::class)->register(
        new ShortageResolverDefinition('no_auto_option', NoAutoOptionResolver::class),
    );

    $opportunity = autoResolveOpportunity('no_auto_option');

    // Resolver is auto-executable & can resolve, but its only option is not flagged
    // auto-executable → autoExecutableOption() returns null → resolveItem continues
    // → nothing executed.
    expect(app(ShortageAutoResolver::class)->resolve($opportunity))->toBe(0)
        ->and(ShortageResolution::query()->count())->toBe(0);
});

it('returns zero when an item on an auto-resolve order has no shortage', function () {
    app()->bind(NoAutoOptionResolver::class);
    app(ShortageResolverRegistry::class)->register(
        new ShortageResolverDefinition('no_auto_option', NoAutoOptionResolver::class),
    );

    // Plenty of stock → the item is fully serviceable → resolveItem hits the early
    // `null shortage` return (line 80).
    $store = Store::factory()->create([
        'timezone' => 'UTC',
        'shortage_auto_resolve_enabled' => true,
        'shortage_preferred_resolvers' => ['no_auto_option'],
    ]);
    $product = Product::factory()->rental()->bulk()->create(['track_availability' => true]);
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity_held' => 50,
    ]);
    $opportunity = Opportunity::factory()->create([
        'store_id' => $store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-05T17:00:00Z',
    ]);
    OpportunityItem::factory()->create([
        'opportunity_id' => $opportunity->id,
        'item_type' => 'product',
        'itemable_type' => Product::class,
        'itemable_id' => $product->id,
        'quantity' => 2,
    ]);

    expect(app(ShortageAutoResolver::class)->resolve($opportunity->fresh()))->toBe(0);
});
