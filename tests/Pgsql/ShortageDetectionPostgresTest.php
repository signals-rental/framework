<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\CreateOpportunity;
use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\AvailabilityResolution;
use App\Enums\DemandPhase;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Services\Shortages\ShortageDetector;
use App\ValueObjects\Shortage;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Tests\Concerns\UsesPostgres;

/*
|--------------------------------------------------------------------------
| PostgreSQL shortage-detection lane
|--------------------------------------------------------------------------
|
| Proves shortage detection against real Postgres, where the demand overlap is
| the authoritative native `tstzrange &&` path (not the SQLite scalar fallback).
| Confirms that a competing demand overlapping the line's window reduces the
| available figure the detector compares against, and that an excluded own-demand
| does not make the line short against itself. Skips when Postgres is unreachable.
|
| Run the lane:
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

    $this->store = Store::factory()->create(['timezone' => 'UTC']);
    $this->detector = app(ShortageDetector::class);
});

it('detects a shortage from a tstzrange-overlapping competing demand', function () {
    $product = Product::factory()->rental()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 5,
    ]);

    // Competing demand whose period overlaps the line's window. The factory's
    // window() state writes the native tstzrange `period` column on Postgres.
    Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window(Carbon::parse('2026-07-03T09:00:00Z'), Carbon::parse('2026-07-04T17:00:00Z'))
        ->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity' => 4,
            'source_type' => 'opportunity_item',
            'source_id' => 555001,
            'metadata' => [],
        ]);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'PG shortage',
        'store_id' => $this->store->id,
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

    $shortages = $this->detector->forOpportunity($opportunity->fresh(['items']));

    expect($shortages)->toHaveCount(1);

    /** @var Shortage $shortage */
    $shortage = $shortages->first();

    expect($shortage->requestedQuantity)->toBe(3)
        ->and($shortage->availableQuantity)->toBe(1)
        ->and($shortage->shortfall)->toBe(2);
});

it('does not flag a shortage for a non-overlapping competing demand', function () {
    $product = Product::factory()->rental()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 5,
    ]);

    // Competing demand entirely OUTSIDE the line's window — no tstzrange overlap.
    Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window(Carbon::parse('2026-08-01T09:00:00Z'), Carbon::parse('2026-08-02T17:00:00Z'))
        ->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity' => 4,
            'source_type' => 'opportunity_item',
            'source_id' => 555002,
            'metadata' => [],
        ]);

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'PG no shortage',
        'store_id' => $this->store->id,
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

    expect($this->detector->forOpportunity($opportunity->fresh(['items'])))->toBeEmpty();
});
