<?php

use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Enums\AssetAssignmentStatus;
use App\Enums\AvailabilityResolution;
use App\Enums\DemandPhase;
use App\Enums\OpportunityStatus;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Services\Availability\OpportunityItemDemandResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsesPostgres;

/*
|--------------------------------------------------------------------------
| PostgreSQL opportunity-item demand resolver lane
|--------------------------------------------------------------------------
|
| Exercises OpportunityItemDemandResolver::persist() paths that write the
| native `period` tstzrange column, including the degenerate-window guard when
| an asset is returned before its planned start (SQLSTATE 22000 on Postgres if
| lower > upper). The SQLite suite has no `period` column and accepts inverted
| scalar bounds silently.
|
|   php artisan test --compact --group=pgsql
|
*/

uses(UsesPostgres::class)->group('pgsql');

beforeEach(function () {
    $this->app->bind(AvailabilityResolutionProvider::class, fn () => new class implements AvailabilityResolutionProvider
    {
        public function resolve(): AvailabilityResolution
        {
            return AvailabilityResolution::Daily;
        }
    });

    $this->store = Store::factory()->create(['timezone' => 'UTC']);
    $this->resolver = app(OpportunityItemDemandResolver::class);
});

/**
 * @param  array<string, mixed>  $itemAttributes
 */
function pgDemandItem(Product $product, Store $store, OpportunityStatus $status, array $itemAttributes = []): OpportunityItem
{
    $opportunity = Opportunity::factory()->create([
        'state' => $status->state()->value,
        'status' => $status->statusValue(),
        'store_id' => $store->id,
        'starts_at' => Carbon::parse('2026-12-01T09:00:00Z'),
        'ends_at' => Carbon::parse('2026-12-05T17:00:00Z'),
    ]);

    return OpportunityItem::factory()->for($opportunity)->create(array_merge([
        'itemable_type' => Product::class,
        'itemable_id' => $product->id,
        'quantity' => 1,
    ], $itemAttributes));
}

it('writes a bulk demand row with a valid tstzrange period on PostgreSQL', function () {
    $product = Product::factory()->bulk()->create([
        'buffer_before_minutes' => 0,
        'post_rent_unavailability' => 0,
    ]);

    $item = pgDemandItem($product, $this->store, OpportunityStatus::OrderActive, ['quantity' => 2]);

    $this->resolver->syncDemands($item);

    $demand = Demand::query()->where('source_id', $item->id)->sole();

    $bounds = DB::connection('pgsql_testing')
        ->selectOne(
            'SELECT lower(period) AS lower_bound, upper(period) AS upper_bound, isempty(period) AS is_empty FROM demands WHERE id = ?',
            [$demand->id],
        );

    expect($bounds->is_empty)->toBeFalse()
        ->and(Carbon::parse($bounds->lower_bound)->toIso8601String())
        ->toBe('2026-12-01T09:00:00+00:00')
        ->and(Carbon::parse($bounds->upper_bound)->toIso8601String())
        ->toBe('2026-12-05T17:00:00+00:00');
});

it('persists a non-inverted tstzrange when an asset is returned before its planned start', function () {
    $product = Product::factory()->serialised()->create([
        'buffer_before_minutes' => 0,
        'post_rent_unavailability' => 0,
    ]);

    $item = pgDemandItem($product, $this->store, OpportunityStatus::OrderDispatched);

    $asset = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
    ]);

    OpportunityItemAsset::factory()->for($item, 'item')->create([
        'stock_level_id' => $asset->id,
        'status' => AssetAssignmentStatus::CheckedIn->value,
        'dispatched_at' => Carbon::parse('2026-11-28T09:00:00Z'),
        'returned_at' => Carbon::parse('2026-11-29T09:00:00Z'),
        'checked_at' => null,
    ]);

    $this->resolver->syncDemands($item);

    $demand = Demand::query()->where('source_id', $item->id)->where('asset_id', $asset->id)->sole();

    expect($demand->phase)->toBe(DemandPhase::Closed)
        ->and($demand->starts_at->lessThanOrEqualTo($demand->ends_at))->toBeTrue()
        ->and($demand->ends_at->toIso8601String())->toBe('2026-11-29T09:00:00+00:00');

    $bounds = DB::connection('pgsql_testing')
        ->selectOne(
            'SELECT lower(period) AS lower_bound, upper(period) AS upper_bound FROM demands WHERE id = ?',
            [$demand->id],
        );

    expect(Carbon::parse($bounds->lower_bound)->lessThanOrEqualTo(Carbon::parse($bounds->upper_bound)))
        ->toBeTrue()
        ->and(Carbon::parse($bounds->upper_bound)->toIso8601String())
        ->toBe('2026-11-29T09:00:00+00:00');
});

it('contracts the asset period tstzrange upper bound to the actual return time', function () {
    $product = Product::factory()->serialised()->create([
        'buffer_before_minutes' => 0,
        'post_rent_unavailability' => 0,
    ]);

    $item = pgDemandItem($product, $this->store, OpportunityStatus::OrderDispatched);

    $asset = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
    ]);

    OpportunityItemAsset::factory()->for($item, 'item')->create([
        'stock_level_id' => $asset->id,
        'status' => AssetAssignmentStatus::CheckedIn->value,
        'dispatched_at' => Carbon::parse('2026-12-01T09:00:00Z'),
        'returned_at' => Carbon::parse('2026-12-02T10:00:00Z'),
        'checked_at' => Carbon::parse('2026-12-02T11:00:00Z'),
    ]);

    $this->resolver->syncDemands($item);

    $demand = Demand::query()->where('asset_id', $asset->id)->sole();

    $upper = DB::connection('pgsql_testing')
        ->table('demands')
        ->where('id', $demand->id)
        ->selectRaw('upper(period) AS upper_bound')
        ->value('upper_bound');

    expect(Carbon::parse($upper)->lessThan(Carbon::parse('2026-12-05T17:00:00Z')))->toBeTrue();
});
