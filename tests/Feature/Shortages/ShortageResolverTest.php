<?php

use App\Enums\AvailabilityEventType;
use App\Enums\DemandPhase;
use App\Enums\OpportunityState;
use App\Enums\ShortageResolutionStatus;
use App\Enums\ShortageResolutionType;
use App\Enums\StockMethod;
use App\Models\AvailabilityEvent;
use App\Models\Demand;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Services\Shortages\Resolvers\DateShiftResolver;
use App\Services\Shortages\Resolvers\PartialFulfilmentResolver;
use App\Services\Shortages\Resolvers\QuoteReallocationResolver;
use App\Services\Shortages\Resolvers\SubstitutionResolver;
use App\Services\Shortages\Resolvers\WaitlistResolver;
use App\Services\Shortages\Resolvers\WarehouseTransferResolver;
use App\Services\Shortages\ShortageResolverRegistry;
use App\ValueObjects\ResolutionOption;
use App\ValueObjects\Shortage;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    $this->registry = app(ShortageResolverRegistry::class);
});

/**
 * A bulk shortage VO backed by real product/store/item rows so the resolution
 * and availability-event foreign keys are satisfiable.
 */
function bulkShortage(int $shortfall = 2, int $available = 1): Shortage
{
    $store = Store::query()->first() ?? Store::factory()->create();
    $product = Product::factory()->rental()->bulk()->create();
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
        requestedQuantity: $available + $shortfall,
        availableQuantity: $available,
        trackingType: StockMethod::Bulk,
        startsAt: Carbon::parse('2026-07-01T09:00:00Z'),
        endsAt: Carbon::parse('2026-07-05T17:00:00Z'),
        isCritical: false,
    );
}

it('registers all six built-in resolvers in priority order', function () {
    $keys = array_map(fn ($r): string => $r->key(), $this->registry->all());

    expect($keys)->toBe(['reallocate', 'substitute', 'transfer', 'date_shift', 'partial', 'waitlist']);
});

it('resolves a resolver instance from the container by key', function () {
    expect($this->registry->resolve('partial'))->toBeInstanceOf(PartialFulfilmentResolver::class)
        ->and($this->registry->has('waitlist'))->toBeTrue()
        ->and($this->registry->has('nope'))->toBeFalse();
});

it('marks no built-in resolver auto-executable (every option needs business judgement per spec)', function () {
    // Spec §4.1/§4.4/§4.5/§4.6: reallocate, date_shift, partial and waitlist are
    // all "Auto-executable: No"; substitute/transfer are "configurable" and
    // default to false until a relationship/pair opts in. So the auto-resolver
    // never silently applies any built-in resolver.
    foreach ($this->registry->all() as $resolver) {
        expect($resolver->isAutoExecutable())->toBeFalse();
    }

    expect(app(PartialFulfilmentResolver::class)->isAutoExecutable())->toBeFalse()
        ->and(app(WaitlistResolver::class)->isAutoExecutable())->toBeFalse()
        ->and(app(QuoteReallocationResolver::class)->isAutoExecutable())->toBeFalse();
});

it('excludes the transfer resolver in a single-warehouse store', function () {
    Store::factory()->create(); // exactly one store

    $applicable = array_map(fn ($r): string => $r->key(), $this->registry->applicableTo(bulkShortage()));

    expect($applicable)->not->toContain('transfer');
});

it('includes the transfer resolver when a second warehouse exists', function () {
    Store::factory()->count(2)->create();

    expect(app(WarehouseTransferResolver::class)->canResolve(bulkShortage()))->toBeTrue();
});

it('applies the partial resolver, recording a confirmed resolution and its item', function () {
    $shortage = bulkShortage(shortfall: 2, available: 4);
    $resolver = app(PartialFulfilmentResolver::class);

    $options = $resolver->getOptions($shortage);
    expect($options)->toHaveCount(1)
        ->and($options[0]->quantityResolved)->toBe(4)
        ->and($options[0]->autoExecutable)->toBeFalse()
        ->and($options[0]->requiresConfirmation)->toBeTrue();

    $result = $resolver->apply($shortage, $options[0]);

    expect($result->success)->toBeTrue()
        ->and($result->status)->toBe(ShortageResolutionStatus::Confirmed)
        ->and($result->resolution)->not->toBeNull();

    $this->assertDatabaseHas('shortage_resolutions', [
        'id' => $result->resolution->id,
        'resolver_key' => 'partial',
        'resolution_type' => ShortageResolutionType::Partial->value,
        'status' => ShortageResolutionStatus::Confirmed->value,
        'quantity_resolved' => 4,
    ]);

    $this->assertDatabaseHas('shortage_resolution_items', [
        'shortage_resolution_id' => $result->resolution->id,
        'opportunity_item_id' => $shortage->opportunityItemId,
        'quantity_allocated' => 4,
    ]);
});

it('records the waitlist resolution as monitoring', function () {
    $shortage = bulkShortage();
    $resolver = app(WaitlistResolver::class);

    $result = $resolver->apply($shortage, $resolver->getOptions($shortage)[0]);

    expect($result->status)->toBe(ShortageResolutionStatus::Monitoring)
        ->and($result->requiresFollowup)->toBeTrue();

    $this->assertDatabaseHas('shortage_resolutions', [
        'id' => $result->resolution->id,
        'resolver_key' => 'waitlist',
        'status' => ShortageResolutionStatus::Monitoring->value,
    ]);
});

it('keeps the substitute resolver dormant while the product_substitutions table is absent', function () {
    // The product_substitutions table is a future domain — it does not exist yet.
    expect(Schema::hasTable('product_substitutions'))->toBeFalse();

    $resolver = app(SubstitutionResolver::class);

    expect($resolver->canResolve(bulkShortage()))->toBeFalse()
        ->and($resolver->getOptions(bulkShortage()))->toBe([]);
});

it('checks the product_substitutions table at most once across repeated canResolve calls', function () {
    $spy = Schema::spy();
    Schema::shouldReceive('hasTable')->with('product_substitutions')->andReturn(false);

    $resolver = app(SubstitutionResolver::class);

    // Many detection cycles share one resolver instance — only one schema lookup.
    $resolver->canResolve(bulkShortage());
    $resolver->canResolve(bulkShortage());
    $resolver->canResolve(bulkShortage());

    $spy->shouldHaveReceived('hasTable')->with('product_substitutions')->once();
});

it('still records substitution intent as pending when applied directly', function () {
    $shortage = bulkShortage();
    $resolver = app(SubstitutionResolver::class);

    $option = new ResolutionOption(
        resolverKey: 'substitute',
        type: ShortageResolutionType::Substitute,
        label: 'Substitute',
        description: 'Substitute an alternative product',
        quantityResolved: $shortage->remainingShortfall(),
        isPartial: false,
        autoExecutable: false,
        metadata: ['substitute_product_id' => 999],
    );

    $result = $resolver->apply($shortage, $option);

    expect($result->status)->toBe(ShortageResolutionStatus::Pending)
        ->and($result->resolution->metadata['pending_dependency'])->toBe('product_substitutions')
        ->and($result->resolution->metadata['substitute_product_id'])->toBe(999);

    $this->assertDatabaseHas('shortage_resolutions', [
        'id' => $result->resolution->id,
        'resolver_key' => 'substitute',
        'resolution_type' => ShortageResolutionType::Substitute->value,
        'status' => ShortageResolutionStatus::Pending->value,
    ]);
});

/**
 * Create an ACTIVE demand held by an unconfirmed quote (opportunity state =
 * Quotation) on the shortage's product/store, overlapping its window — exactly
 * the stock the QuoteReallocationResolver looks for.
 */
function competingQuoteDemand(Shortage $shortage): void
{
    Demand::factory()
        ->phase(DemandPhase::Held)
        ->window($shortage->startsAt, $shortage->endsAt)
        ->create([
            'product_id' => $shortage->productId,
            'store_id' => $shortage->storeId,
            'quantity' => 1,
            'source_type' => 'opportunity_item',
            'source_id' => 987654,
            'metadata' => ['opportunity_state' => OpportunityState::Quotation->value],
        ]);
}

it('offers the reallocate option only when a competing quote demand exists', function () {
    $shortage = bulkShortage();
    $resolver = app(QuoteReallocationResolver::class);

    // No competing quote holds stock → nothing to reallocate from.
    expect($resolver->canResolve($shortage))->toBeFalse()
        ->and($resolver->getOptions($shortage))->toBe([]);
});

it('offers the reallocate option when an unconfirmed quote holds overlapping stock', function () {
    $shortage = bulkShortage();
    competingQuoteDemand($shortage);

    $resolver = app(QuoteReallocationResolver::class);

    expect($resolver->canResolve($shortage))->toBeTrue()
        ->and($resolver->getOptions($shortage))->toHaveCount(1);
});

it('does not offer reallocate when the only overlapping demand is a confirmed order', function () {
    $shortage = bulkShortage();

    // A committed (order-state) demand is NOT a reallocation candidate.
    Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window($shortage->startsAt, $shortage->endsAt)
        ->create([
            'product_id' => $shortage->productId,
            'store_id' => $shortage->storeId,
            'quantity' => 1,
            'source_type' => 'opportunity_item',
            'source_id' => 111222,
            'metadata' => ['opportunity_state' => OpportunityState::Order->value],
        ]);

    expect(app(QuoteReallocationResolver::class)->canResolve($shortage))->toBeFalse();
});

it('records reallocation as pending intent awaiting the quote-release domain', function () {
    $shortage = bulkShortage();
    competingQuoteDemand($shortage);
    $resolver = app(QuoteReallocationResolver::class);

    $result = $resolver->apply($shortage, $resolver->getOptions($shortage)[0]);

    expect($result->status)->toBe(ShortageResolutionStatus::Pending)
        ->and($result->requiresFollowup)->toBeTrue()
        ->and($result->resolution->metadata['pending_dependency'])->toBe('quote_release');
});

it('emits a shortage.resolution.created event when a resolution is applied', function () {
    $shortage = bulkShortage(shortfall: 2, available: 4);
    $resolver = app(PartialFulfilmentResolver::class);

    $resolver->apply($shortage, $resolver->getOptions($shortage)[0]);

    expect(
        AvailabilityEvent::query()
            ->where('event_type', AvailabilityEventType::ShortageResolutionCreated->value)
            ->where('source_type', 'shortage_resolution')
            ->exists()
    )->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Bug #4 — DateShiftResolver reversed-window regression (Carbon-3 signed diff)
|--------------------------------------------------------------------------
|
| Carbon 3's diffInSeconds is SIGNED. The resolver derived the shift duration
| with `endsAt->diffInSeconds(startsAt)` which is NEGATIVE for a forward window,
| so each candidate window was built as `start->addSeconds(negative)` → end BEFORE
| start. That reversed window was handed to AvailabilityService::availableForItem,
| whose Demand::overlapping() scope builds `tstzrange(start, end)` on Postgres and
| threw `SQLSTATE[22000] range lower bound must be less than or equal to range
| upper bound`. The PG reproduction lives in tests/Pgsql; here we lock the
| driver-agnostic logic: every candidate window must be forward (start <= end).
*/

it('builds only forward (start <= end) candidate windows for a valid shortage', function () {
    // Real stock so the resolver actually returns options (it only offers a window
    // where the full quantity is free) — exercising the metadata window builder.
    $store = Store::factory()->create(['timezone' => 'UTC']);
    $product = Product::factory()->rental()->bulk()->create(['track_availability' => true]);
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity_held' => 10,
    ]);
    $item = OpportunityItem::factory()->create([
        'item_type' => 'product',
        'item_id' => $product->id,
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
        startsAt: Carbon::parse('2026-07-10T09:00:00Z'),
        endsAt: Carbon::parse('2026-07-12T17:00:00Z'),
        isCritical: false,
    );

    $resolver = app(DateShiftResolver::class);

    // Pre-fix this loop built reversed windows; the resolver must not throw and
    // every returned window's shifted range must be forward.
    $options = $resolver->getOptions($shortage);

    expect($options)->not->toBeEmpty();

    foreach ($options as $option) {
        $start = Carbon::parse($option->metadata['shifted_starts_at']);
        $end = Carbon::parse($option->metadata['shifted_ends_at']);

        expect($start->lessThanOrEqualTo($end))->toBeTrue()
            // The window must preserve the requested span (2 days 8 hours), proving
            // the abs() duration is positive (a negative duration would collapse it).
            ->and($start->diffInSeconds($end, absolute: true))->toBe(
                Carbon::parse('2026-07-10T09:00:00Z')->diffInSeconds('2026-07-12T17:00:00Z', absolute: true),
            );
    }
});

it('runs getOptions for a single-day (today→tomorrow) shortage without throwing', function () {
    // The demo opportunity had NULL dates, defaulting to a today→tomorrow window —
    // the exact case that previously produced the reversed tstzrange.
    $store = Store::factory()->create(['timezone' => 'UTC']);
    $product = Product::factory()->rental()->bulk()->create(['track_availability' => true]);
    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity_held' => 5,
    ]);
    $item = OpportunityItem::factory()->create([
        'item_type' => 'product',
        'item_id' => $product->id,
    ]);

    $today = Carbon::today('UTC')->setTime(9, 0);

    $shortage = Shortage::make(
        opportunityItemId: $item->id,
        opportunityId: $item->opportunity_id,
        productId: $product->id,
        productName: $product->name,
        storeId: $store->id,
        requestedQuantity: 2,
        availableQuantity: 0,
        trackingType: StockMethod::Bulk,
        startsAt: $today->copy(),
        endsAt: $today->copy()->addDay(),
        isCritical: false,
    );

    $resolver = app(DateShiftResolver::class);

    expect(fn () => $resolver->getOptions($shortage))->not->toThrow(Throwable::class);
});
