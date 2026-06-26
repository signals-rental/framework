<?php

use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Enums\AvailabilityResolution;
use App\Enums\DemandPhase;
use App\Enums\ShortageResolutionType;
use App\Enums\StockMethod;
use App\Models\Demand;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Services\Shortages\Resolvers\WarehouseTransferResolver;
use App\ValueObjects\Shortage;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Tests\Concerns\UsesPostgres;

/*
|--------------------------------------------------------------------------
| PostgreSQL warehouse-transfer resolver lane
|--------------------------------------------------------------------------
|
| Exercises WarehouseTransferResolver::getOptions() against the native
| `demands.period && tstzrange(...)` overlap path. The SQLite default suite
| falls back to scalar buffered-bounds overlap, so cross-store availability
| math for competing demands with real tstzrange periods is proven here only.
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

    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());

    $this->resolver = app(WarehouseTransferResolver::class);
});

function pgTransferShortage(Store $home, Product $product, int $shortfall = 2, int $availableAtHome = 1): Shortage
{
    $item = OpportunityItem::factory()->create([
        'item_type' => 'product',
        'itemable_id' => $product->id,
    ]);

    return Shortage::make(
        opportunityItemId: $item->id,
        opportunityId: $item->opportunity_id,
        productId: $product->id,
        productName: $product->name,
        storeId: $home->id,
        requestedQuantity: $availableAtHome + $shortfall,
        availableQuantity: $availableAtHome,
        trackingType: StockMethod::Bulk,
        startsAt: Carbon::parse('2026-07-01T09:00:00Z'),
        endsAt: Carbon::parse('2026-07-05T17:00:00Z'),
        isCritical: false,
    );
}

it('offers transfer coverage from a remote store using native tstzrange overlap', function () {
    $home = Store::factory()->create(['name' => 'London', 'timezone' => 'UTC']);
    $remote = Store::factory()->create(['name' => 'Manchester', 'timezone' => 'UTC']);

    $product = Product::factory()->rental()->bulk()->create(['track_availability' => true]);

    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $home->id,
        'quantity_held' => 1,
    ]);
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $remote->id,
        'quantity_held' => 6,
    ]);

    $shortage = pgTransferShortage($home, $product, shortfall: 2, availableAtHome: 1);

    $options = $this->resolver->getOptions($shortage);

    expect($options)->toHaveCount(1)
        ->and($options[0]->type)->toBe(ShortageResolutionType::Transfer)
        ->and($options[0]->quantityResolved)->toBe(2)
        ->and($options[0]->metadata['source_store_id'])->toBe($remote->id);
});

it('skips remote stores whose free stock is fully consumed by overlapping tstzrange demands', function () {
    $home = Store::factory()->create(['timezone' => 'UTC']);
    $remote = Store::factory()->create(['name' => 'Leeds', 'timezone' => 'UTC']);

    $product = Product::factory()->rental()->bulk()->create(['track_availability' => true]);

    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $home->id,
        'quantity_held' => 0,
    ]);
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $remote->id,
        'quantity_held' => 3,
    ]);

    // Competing demand whose `period` tstzrange overlaps the shortage window.
    Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window(Carbon::parse('2026-07-01T09:00:00Z'), Carbon::parse('2026-07-05T17:00:00Z'))
        ->create([
            'product_id' => $product->id,
            'store_id' => $remote->id,
            'quantity' => 3,
            'source_type' => 'opportunity_item',
            'source_id' => 777001,
        ]);

    $shortage = pgTransferShortage($home, $product, shortfall: 2, availableAtHome: 0);

    expect($this->resolver->getOptions($shortage))->toBe([]);
});

it('offers partial transfer when tstzrange overlap leaves only some units free', function () {
    $home = Store::factory()->create(['timezone' => 'UTC']);
    $remote = Store::factory()->create(['timezone' => 'UTC']);

    $product = Product::factory()->rental()->bulk()->create(['track_availability' => true]);

    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $home->id,
        'quantity_held' => 0,
    ]);
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $remote->id,
        'quantity_held' => 4,
    ]);

    Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window(Carbon::parse('2026-07-01T09:00:00Z'), Carbon::parse('2026-07-05T17:00:00Z'))
        ->create([
            'product_id' => $product->id,
            'store_id' => $remote->id,
            'quantity' => 2,
            'source_type' => 'opportunity_item',
            'source_id' => 777002,
        ]);

    $shortage = pgTransferShortage($home, $product, shortfall: 3, availableAtHome: 0);

    $options = $this->resolver->getOptions($shortage);

    expect($options)->toHaveCount(1)
        ->and($options[0]->quantityResolved)->toBe(2)
        ->and($options[0]->isPartial)->toBeTrue();
});

it('ignores competing demands whose tstzrange does not overlap the shortage window', function () {
    $home = Store::factory()->create(['timezone' => 'UTC']);
    $remote = Store::factory()->create(['timezone' => 'UTC']);

    $product = Product::factory()->rental()->bulk()->create(['track_availability' => true]);

    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $home->id,
        'quantity_held' => 0,
    ]);
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $remote->id,
        'quantity_held' => 2,
    ]);

    // Non-overlapping window — must not reduce Jul availability at the remote store.
    Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window(Carbon::parse('2026-08-01T09:00:00Z'), Carbon::parse('2026-08-05T17:00:00Z'))
        ->create([
            'product_id' => $product->id,
            'store_id' => $remote->id,
            'quantity' => 2,
            'source_type' => 'opportunity_item',
            'source_id' => 777003,
        ]);

    $shortage = pgTransferShortage($home, $product, shortfall: 2, availableAtHome: 0);

    $options = $this->resolver->getOptions($shortage);

    expect($options)->toHaveCount(1)
        ->and($options[0]->quantityResolved)->toBe(2)
        ->and($options[0]->isPartial)->toBeFalse();
});
