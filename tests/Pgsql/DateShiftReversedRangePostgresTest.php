<?php

use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Enums\AvailabilityResolution;
use App\Enums\StockMethod;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\Shortages\Resolvers\DateShiftResolver;
use App\ValueObjects\ResolutionOption;
use App\ValueObjects\Shortage;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Tests\Concerns\UsesPostgres;

/*
|--------------------------------------------------------------------------
| Bug #4 — DateShiftResolver reversed tstzrange (PostgreSQL lane)
|--------------------------------------------------------------------------
|
| The /shortages resolver-options path (ShortageController::resolvers →
| DateShiftResolver::getOptions → AvailabilityService::availableForItem →
| Demand::overlapping) builds `tstzrange(?, ?)` on Postgres. Carbon-3's signed
| diffInSeconds made the resolver compute a NEGATIVE shift duration for a forward
| window, so each candidate window ended BEFORE it started. Postgres then threw:
|
|   SQLSTATE[22000]: range lower bound must be less than or equal to range upper bound
|
| This is the only place the bug actually 500s — SQLite's scalar overlap fallback
| silently accepts a reversed window, so the SQLite lane cannot reproduce the
| QueryException. Two fixes are exercised here:
|   1. DateShiftResolver uses `abs($startsAt->diffInSeconds($endsAt))`.
|   2. AvailabilityService::availableForItem swaps a reversed (from > to) window.
|
| Pre-fix: getOptions() threw QueryException at the first candidate window;
| availableForItem(to, from) threw immediately at the tstzrange literal.
| Post-fix: both return cleanly. Skips when Postgres is unreachable.
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
});

/**
 * A bulk shortage VO over a forward window, backed by real product/stock/item rows
 * so the resolver can read availability and the foreign keys are satisfiable.
 */
function pgForwardShortage(Store $store, Carbon $start, Carbon $end, int $stock = 10): Shortage
{
    $product = Product::factory()->rental()->bulk()->create(['track_availability' => true]);
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity_held' => $stock,
    ]);
    $item = OpportunityItem::factory()->create([
        'item_type' => 'product',
        'item_id' => $product->id,
    ]);

    return Shortage::make(
        opportunityItemId: $item->id,
        opportunityId: $item->opportunity_id,
        productId: $product->id,
        productName: $product->name,
        storeId: $store->id,
        requestedQuantity: 3,
        availableQuantity: 1,
        trackingType: StockMethod::Bulk,
        startsAt: $start,
        endsAt: $end,
        isCritical: false,
    );
}

it('builds date-shift options without throwing a reversed-tstzrange QueryException', function () {
    // A forward window — pre-fix the resolver computed a negative duration, built
    // each candidate as start->addSeconds(negative) → end < start, and the first
    // availableForItem() call threw SQLSTATE[22000] on the real tstzrange literal.
    $shortage = pgForwardShortage(
        $this->store,
        Carbon::parse('2026-07-10T09:00:00Z'),
        Carbon::parse('2026-07-12T17:00:00Z'),
    );

    $resolver = app(DateShiftResolver::class);

    $options = $resolver->getOptions($shortage);

    // It must return real (forward) options, and every shifted window is forward.
    expect($options)->not->toBeEmpty();

    foreach ($options as $option) {
        expect($option)->toBeInstanceOf(ResolutionOption::class);
        $start = Carbon::parse($option->metadata['shifted_starts_at']);
        $end = Carbon::parse($option->metadata['shifted_ends_at']);
        expect($start->lessThanOrEqualTo($end))->toBeTrue();
    }
});

it('reproduces the today→tomorrow null-dates demo case without a QueryException', function () {
    // The demo opportunity had NULL dates → today→tomorrow default — the exact
    // window that surfaced the reversed-range 500 on /shortages.
    $today = Carbon::today('UTC')->setTime(9, 0);

    $shortage = pgForwardShortage($this->store, $today->copy(), $today->copy()->addDay());

    $resolver = app(DateShiftResolver::class);

    expect(fn () => $resolver->getOptions($shortage))->not->toThrow(QueryException::class);
});

it('normalises a reversed window in availableForItem against real tstzrange', function () {
    // Calling the engine directly with from > to: pre-fix this built
    // tstzrange(later, earlier) and threw SQLSTATE[22000]; the defensive swap now
    // makes it equal the forward read.
    $product = Product::factory()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 6,
    ]);

    $service = app(AvailabilityService::class);
    $from = Carbon::parse('2026-07-10T09:00:00Z');
    $to = Carbon::parse('2026-07-12T17:00:00Z');

    $forward = $service->availableForItem($product->id, $this->store->id, $from, $to, 'opportunity_item', 123456);

    // Reversed bounds — must not throw the tstzrange QueryException, and must agree.
    $reversed = null;
    expect(function () use ($service, $product, $to, $from, &$reversed) {
        $reversed = $service->availableForItem($product->id, $this->store->id, $to, $from, 'opportunity_item', 123456);
    })->not->toThrow(QueryException::class);

    expect($reversed)->toBe($forward)
        ->and($forward)->toBe(6);
});
