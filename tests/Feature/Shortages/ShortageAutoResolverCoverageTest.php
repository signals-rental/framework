<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Contracts\ShortageResolverContract;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\AvailabilityResolution;
use App\Enums\DemandPhase;
use App\Enums\ShortagePolicy;
use App\Enums\ShortageResolutionStatus;
use App\Enums\ShortageResolutionType;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ShortageResolution;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Services\Shortages\Resolvers\AbstractShortageResolver;
use App\Services\Shortages\ShortageAutoResolver;
use App\Services\Shortages\ShortageEventRecorder;
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
 * Test double: auto-executable resolver that records a confirmed partial cover.
 */
class AutoExecCoverageResolver extends AbstractShortageResolver implements ShortageResolverContract
{
    public function __construct(ShortageEventRecorder $events)
    {
        parent::__construct($events);
    }

    public function key(): string
    {
        return 'auto_exec_cov';
    }

    public function name(): string
    {
        return 'Auto exec coverage';
    }

    public function priority(): int
    {
        return 5;
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
                label: 'Auto cover all remaining',
                description: 'Test auto resolver',
                quantityResolved: $shortage->remainingShortfall(),
                isPartial: false,
                autoExecutable: true,
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

        return ResolutionResult::confirmed($resolution, 'Auto-applied in test.');
    }

    protected function resolutionType(): ShortageResolutionType
    {
        return ShortageResolutionType::Partial;
    }
}

/**
 * @param  array<string, mixed>  $storeConfig
 */
function autoResolverQuotation(array $storeConfig): Opportunity
{
    $store = Store::factory()->create(['timezone' => 'UTC'] + $storeConfig);
    $product = Product::factory()->rental()->bulk()->create(['track_availability' => true]);
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
            'source_id' => 999101,
        ]);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Auto resolver coverage',
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

function registerAutoExecResolver(): void
{
    app()->bind(AutoExecCoverageResolver::class);
    app(ShortageResolverRegistry::class)->register(
        new ShortageResolverDefinition('auto_exec_cov', AutoExecCoverageResolver::class),
    );
}

it('auto-executes a preferred auto-executable resolver and records a resolution', function () {
    registerAutoExecResolver();

    $opportunity = autoResolverQuotation([
        'shortage_policy' => ShortagePolicy::Allow->value,
        'shortage_auto_resolve_enabled' => true,
        'shortage_preferred_resolvers' => ['auto_exec_cov'],
    ]);

    $count = app(ShortageAutoResolver::class)->resolve($opportunity);

    expect($count)->toBe(1)
        ->and(ShortageResolution::query()->where('resolver_key', 'auto_exec_cov')->count())->toBe(1);
});

it('skips unknown preferred resolver keys while honouring known ones', function () {
    registerAutoExecResolver();

    $opportunity = autoResolverQuotation([
        'shortage_policy' => ShortagePolicy::Allow->value,
        'shortage_auto_resolve_enabled' => true,
        'shortage_preferred_resolvers' => ['missing_key', 'auto_exec_cov'],
    ]);

    expect(app(ShortageAutoResolver::class)->resolve($opportunity))->toBe(1);
});

it('returns zero when the opportunity has no store', function () {
    registerAutoExecResolver();

    $opportunity = autoResolverQuotation([
        'shortage_policy' => ShortagePolicy::Allow->value,
        'shortage_auto_resolve_enabled' => true,
        'shortage_preferred_resolvers' => ['auto_exec_cov'],
    ]);

    $opportunity->setRelation('store', null);

    expect(app(ShortageAutoResolver::class)->resolve($opportunity))->toBe(0)
        ->and(ShortageResolution::query()->count())->toBe(0);
});

it('returns zero when preferred resolver list resolves empty after skipping unknown keys', function () {
    $opportunity = autoResolverQuotation([
        'shortage_policy' => ShortagePolicy::Allow->value,
        'shortage_auto_resolve_enabled' => true,
        'shortage_preferred_resolvers' => ['missing_a', 'missing_b'],
    ]);

    expect(app(ShortageAutoResolver::class)->resolve($opportunity))->toBe(0);
});

it('stops after the shortage is fully covered by a resolution', function () {
    registerAutoExecResolver();

    $opportunity = autoResolverQuotation([
        'shortage_policy' => ShortagePolicy::Allow->value,
        'shortage_auto_resolve_enabled' => true,
        'shortage_preferred_resolvers' => ['auto_exec_cov', 'auto_exec_cov'],
    ]);

    $count = app(ShortageAutoResolver::class)->resolve($opportunity);

    expect($count)->toBe(1)
        ->and(ShortageResolution::query()->count())->toBe(1);
});
