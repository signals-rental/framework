<?php

use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Enums\AvailabilityEventType;
use App\Enums\AvailabilityResolution;
use App\Enums\DemandPhase;
use App\Models\AvailabilityEvent;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use App\Services\Shortages\ItemShortageProbe;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;
use Thunk\Verbs\Facades\Verbs;

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
    $this->probe = app(ItemShortageProbe::class);
});

/**
 * A product-backed line item on an opportunity over the standard window, with an
 * external competing demand removing all but one unit of free stock.
 */
function probedItem(Store $store, int $held, int $competing, int $quantity): OpportunityItem
{
    $product = Product::factory()->rental()->bulk()->create();

    StockLevel::factory()->bulk()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'quantity_held' => $held,
    ]);

    if ($competing > 0) {
        Demand::factory()
            ->phase(DemandPhase::Committed)
            ->window(Carbon::parse('2026-07-01T09:00:00Z'), Carbon::parse('2026-07-05T17:00:00Z'))
            ->create([
                'product_id' => $product->id,
                'store_id' => $store->id,
                'quantity' => $competing,
                'source_type' => 'opportunity_item',
                'source_id' => 555000 + random_int(1, 999),
                'metadata' => [],
            ]);
    }

    $opportunity = Opportunity::factory()->create([
        'store_id' => $store->id,
        'starts_at' => Carbon::parse('2026-07-01T09:00:00Z'),
        'ends_at' => Carbon::parse('2026-07-05T17:00:00Z'),
    ]);

    return OpportunityItem::factory()->create([
        'opportunity_id' => $opportunity->id,
        'name' => $product->name,
        'itemable_type' => Product::class,
        'itemable_id' => $product->id,
        'quantity' => $quantity,
    ]);
}

it('emits shortage.detected when a probed line item is short', function () {
    $item = probedItem($this->store, held: 5, competing: 4, quantity: 3);

    $this->probe->probe($item);

    expect(AvailabilityEvent::query()
        ->where('event_type', AvailabilityEventType::ShortageDetected->value)
        ->where('source_type', 'opportunity_item')
        ->where('source_id', $item->id)
        ->exists())->toBeTrue();
});

it('does not emit when a probed line item is fully serviceable', function () {
    $item = probedItem($this->store, held: 10, competing: 0, quantity: 3);

    $this->probe->probe($item);

    expect(AvailabilityEvent::query()
        ->where('source_type', 'opportunity_item')
        ->where('source_id', $item->id)
        ->whereIn('event_type', [
            AvailabilityEventType::ShortageDetected->value,
            AvailabilityEventType::ShortageResolved->value,
        ])
        ->exists())->toBeFalse();
});

it('emits shortage.cleared only when the line previously had an open shortage', function () {
    $item = probedItem($this->store, held: 5, competing: 4, quantity: 3);

    // First probe: short — emits detected.
    $this->probe->probe($item);

    // Free up stock so the line is now serviceable, then re-probe.
    Demand::query()->where('source_type', 'opportunity_item')
        ->where('product_id', $item->itemable_id)
        ->where('source_type', 'opportunity_item')
        ->whereNot('source_id', $item->id)
        ->delete();

    $this->probe->probe($item->fresh());

    expect(AvailabilityEvent::query()
        ->where('event_type', AvailabilityEventType::ShortageResolved->value)
        ->where('source_type', 'opportunity_item')
        ->where('source_id', $item->id)
        ->exists())->toBeTrue();
});

it('is a no-op while Verbs is replaying', function () {
    $item = probedItem($this->store, held: 5, competing: 4, quantity: 3);

    Verbs::shouldReceive('isReplaying')->andReturnTrue();

    app(ItemShortageProbe::class)->probe($item);

    expect(AvailabilityEvent::query()
        ->where('source_type', 'opportunity_item')
        ->where('source_id', $item->id)
        ->whereIn('event_type', [
            AvailabilityEventType::ShortageDetected->value,
            AvailabilityEventType::ShortageResolved->value,
        ])
        ->exists())->toBeFalse();
});
