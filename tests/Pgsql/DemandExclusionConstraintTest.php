<?php

use App\Enums\DemandPhase;
use App\Models\Demand;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsesPostgres;

/*
|--------------------------------------------------------------------------
| PostgreSQL availability lane
|--------------------------------------------------------------------------
|
| These tests validate the PostgreSQL-only guarantees of the `demands` table:
| the `tstzrange` period column, GiST indexes, and the serialised-asset
| exclusion constraint (`excl_demands_asset_period`). They run against the
| dedicated `pgsql_testing` connection (see config/database.php and
| Tests\Concerns\UsesPostgres) and SKIP cleanly when Postgres is unreachable,
| so the default SQLite suite is unaffected.
|
| Run the lane:
|   php artisan test --compact --group=pgsql
|
| Default (SQLite) suite — these skip:
|   php artisan test --compact
|
*/

uses(UsesPostgres::class)->group('pgsql');

/**
 * Insert a serialised demand row directly, building the tstzrange period from
 * the given window. Bypasses the factory so each test controls the window and
 * activity precisely.
 */
function insertPgSerialisedDemand(
    int $productId,
    int $storeId,
    int $assetId,
    string $start,
    string $end,
    DemandPhase $phase = DemandPhase::Committed,
): void {
    DB::connection('pgsql_testing')->table('demands')->insert([
        'product_id' => $productId,
        'store_id' => $storeId,
        'asset_id' => $assetId,
        'quantity' => 1,
        'period' => DB::raw(sprintf("tstzrange('%s', '%s', '[)')", $start, $end)),
        'starts_at' => $start,
        'ends_at' => $end,
        'source_type' => 'opportunity_item',
        'source_id' => 1,
        'phase' => $phase->value,
        'is_active' => $phase->isActive(),
        'priority' => 0,
        'metadata' => json_encode([]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

beforeEach(function () {
    $this->product = Product::factory()->create();
    $this->store = Store::factory()->create();
    $this->assetA = StockLevel::factory()->serialised()->create([
        'product_id' => $this->product->id,
        'store_id' => $this->store->id,
    ]);
    $this->assetB = StockLevel::factory()->serialised()->create([
        'product_id' => $this->product->id,
        'store_id' => $this->store->id,
    ]);
});

it('confirms the btree_gist extension is installed', function () {
    $exists = DB::connection('pgsql_testing')
        ->selectOne("SELECT 1 AS ok FROM pg_extension WHERE extname = 'btree_gist'");

    expect($exists)->not->toBeNull();
});

it('rejects two active overlapping demands for the same asset (23P01)', function () {
    insertPgSerialisedDemand(
        $this->product->id,
        $this->store->id,
        $this->assetA->id,
        '2026-09-01T09:00:00Z',
        '2026-09-05T17:00:00Z',
    );

    try {
        insertPgSerialisedDemand(
            $this->product->id,
            $this->store->id,
            $this->assetA->id,
            '2026-09-03T09:00:00Z',
            '2026-09-08T17:00:00Z',
        );
        $this->fail('Expected an exclusion-constraint violation but none was thrown.');
    } catch (QueryException $e) {
        // 23P01 = exclusion_violation
        expect($e->getCode())->toBe('23P01')
            ->and($e->getMessage())->toContain('excl_demands_asset_period');
    }
});

it('allows the same asset across non-overlapping windows', function () {
    insertPgSerialisedDemand(
        $this->product->id,
        $this->store->id,
        $this->assetA->id,
        '2026-09-01T09:00:00Z',
        '2026-09-05T17:00:00Z',
    );

    insertPgSerialisedDemand(
        $this->product->id,
        $this->store->id,
        $this->assetA->id,
        '2026-09-05T17:00:00Z',
        '2026-09-10T17:00:00Z',
    );

    expect(DB::connection('pgsql_testing')->table('demands')->count())->toBe(2);
});

it('allows different assets to overlap in time', function () {
    insertPgSerialisedDemand(
        $this->product->id,
        $this->store->id,
        $this->assetA->id,
        '2026-09-01T09:00:00Z',
        '2026-09-05T17:00:00Z',
    );

    insertPgSerialisedDemand(
        $this->product->id,
        $this->store->id,
        $this->assetB->id,
        '2026-09-01T09:00:00Z',
        '2026-09-05T17:00:00Z',
    );

    expect(DB::connection('pgsql_testing')->table('demands')->count())->toBe(2);
});

it('ignores inactive demands in the partial exclusion constraint', function () {
    // An inactive (Draft) demand and a Void demand may overlap an active one
    // for the same asset, because the constraint is WHERE is_active = true.
    insertPgSerialisedDemand(
        $this->product->id,
        $this->store->id,
        $this->assetA->id,
        '2026-09-01T09:00:00Z',
        '2026-09-05T17:00:00Z',
        DemandPhase::Draft,
    );

    insertPgSerialisedDemand(
        $this->product->id,
        $this->store->id,
        $this->assetA->id,
        '2026-09-02T09:00:00Z',
        '2026-09-06T17:00:00Z',
        DemandPhase::Committed,
    );

    insertPgSerialisedDemand(
        $this->product->id,
        $this->store->id,
        $this->assetA->id,
        '2026-09-03T09:00:00Z',
        '2026-09-07T17:00:00Z',
        DemandPhase::Void,
    );

    expect(DB::connection('pgsql_testing')->table('demands')->count())->toBe(3);
});

it('ignores bulk demands (null asset_id) in the exclusion constraint', function () {
    // Two active bulk demands for the same product overlapping in time are fine
    // — the exclusion constraint only applies WHERE asset_id IS NOT NULL.
    Demand::factory()->window(
        Carbon::parse('2026-09-01T09:00:00Z'),
        Carbon::parse('2026-09-05T17:00:00Z'),
    )->create([
        'product_id' => $this->product->id,
        'store_id' => $this->store->id,
        'asset_id' => null,
        'quantity' => 5,
    ]);

    Demand::factory()->window(
        Carbon::parse('2026-09-02T09:00:00Z'),
        Carbon::parse('2026-09-06T17:00:00Z'),
    )->create([
        'product_id' => $this->product->id,
        'store_id' => $this->store->id,
        'asset_id' => null,
        'quantity' => 3,
    ]);

    expect(DB::connection('pgsql_testing')->table('demands')->where('asset_id', null)->count())->toBe(2);
});

it('finds overlapping demands with the tstzrange && operator', function () {
    insertPgSerialisedDemand(
        $this->product->id,
        $this->store->id,
        $this->assetA->id,
        '2026-09-10T00:00:00Z',
        '2026-09-12T00:00:00Z',
    );

    insertPgSerialisedDemand(
        $this->product->id,
        $this->store->id,
        $this->assetB->id,
        '2026-09-01T00:00:00Z',
        '2026-09-05T00:00:00Z',
    );

    $overlapping = Demand::on('pgsql_testing')
        ->overlapping(
            Carbon::parse('2026-09-11T00:00:00Z'),
            Carbon::parse('2026-09-20T00:00:00Z'),
        )
        ->count();

    expect($overlapping)->toBe(1);
});
