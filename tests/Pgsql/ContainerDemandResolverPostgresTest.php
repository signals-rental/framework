<?php

use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Enums\AvailabilityResolution;
use App\Enums\DemandPhase;
use App\Models\Container;
use App\Models\ContainerItem;
use App\Models\Demand;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Services\Availability\ContainerDemandResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsesPostgres;

/*
|--------------------------------------------------------------------------
| PostgreSQL container-demand resolver lane
|--------------------------------------------------------------------------
|
| Proves ContainerDemandResolver::syncDemands() writes the native `period`
| tstzrange column on PostgreSQL (the SQLite schema has no `period` column).
| Also validates that an indefinite kit reservation produces a valid range
| the exclusion constraint can evaluate.
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
    $this->resolver = app(ContainerDemandResolver::class);
});

it('persists a kit container demand with a non-null tstzrange period column', function () {
    $container = Container::factory()->kit()->create(['store_id' => $this->store->id]);
    $product = Product::factory()->serialised()->create();
    $item = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
    ]);

    $membership = ContainerItem::factory()->create([
        'container_id' => $container->id,
        'serialised_item_id' => $item->id,
        'product_id' => $product->id,
        'packed_at' => Carbon::parse('2026-06-01T10:00:00Z'),
        'unpacked_at' => null,
    ]);

    $this->resolver->syncDemands($membership);

    $demand = Demand::query()->where('source_type', 'container')->where('source_id', $membership->id)->sole();

    expect($demand->phase)->toBe(DemandPhase::Committed)
        ->and($demand->asset_id)->toBe($item->id)
        ->and($demand->quantity)->toBe(1);

    $bounds = DB::connection('pgsql_testing')
        ->selectOne(
            'SELECT lower(period) AS lower_bound, upper(period) AS upper_bound, isempty(period) AS is_empty FROM demands WHERE id = ?',
            [$demand->id],
        );

    expect($bounds->is_empty)->toBeFalse()
        ->and(Carbon::parse($bounds->lower_bound)->toIso8601String())
        ->toBe('2026-06-01T10:00:00+00:00')
        ->and(Carbon::parse($bounds->lower_bound)->lessThanOrEqualTo(Carbon::parse($bounds->upper_bound)))
        ->toBeTrue();
});

it('rebuilds the period tstzrange when a membership is re-synced after a store move', function () {
    $container = Container::factory()->kit()->create(['store_id' => $this->store->id]);
    $product = Product::factory()->serialised()->create();
    $otherStore = Store::factory()->create(['timezone' => 'UTC']);

    $item = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
    ]);

    $membership = ContainerItem::factory()->create([
        'container_id' => $container->id,
        'serialised_item_id' => $item->id,
        'product_id' => $product->id,
        'packed_at' => now()->subHour(),
        'unpacked_at' => null,
    ]);

    $this->resolver->syncDemands($membership);
    $firstId = Demand::query()->where('source_id', $membership->id)->value('id');

    $item->update(['store_id' => $otherStore->id]);
    $membership->refresh();

    $this->resolver->syncDemands($membership);

    $demand = Demand::query()->where('source_type', 'container')->where('source_id', $membership->id)->sole();

    expect($demand->id)->not->toBe($firstId)
        ->and($demand->store_id)->toBe($otherStore->id);

    $isEmpty = DB::connection('pgsql_testing')
        ->table('demands')
        ->where('id', $demand->id)
        ->selectRaw('isempty(period) AS is_empty')
        ->value('is_empty');

    expect($isEmpty)->toBeFalse();
});

it('hard-deletes container demands on release so the exclusion constraint sees no stale period', function () {
    $container = Container::factory()->kit()->create(['store_id' => $this->store->id]);
    $product = Product::factory()->serialised()->create();
    $item = StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
    ]);

    $membership = ContainerItem::factory()->create([
        'container_id' => $container->id,
        'serialised_item_id' => $item->id,
        'product_id' => $product->id,
        'packed_at' => now()->subHour(),
        'unpacked_at' => null,
    ]);

    $this->resolver->syncDemands($membership);
    expect(Demand::query()->where('source_id', $membership->id)->count())->toBe(1);

    $this->resolver->releaseDemands($membership);

    expect(Demand::query()->where('source_type', 'container')->where('source_id', $membership->id)->count())
        ->toBe(0);
});
