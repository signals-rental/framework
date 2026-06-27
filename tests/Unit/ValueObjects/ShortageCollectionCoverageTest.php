<?php

use App\Enums\StockMethod;
use App\ValueObjects\Shortage;
use App\ValueObjects\ShortageCollection;
use Illuminate\Support\Carbon;

function shortage(int $itemId, int $requested, int $available, int $resolved = 0): Shortage
{
    return Shortage::make(
        opportunityItemId: $itemId,
        opportunityId: 100,
        productId: 7,
        productName: 'Par Can',
        storeId: 1,
        requestedQuantity: $requested,
        availableQuantity: $available,
        trackingType: StockMethod::Bulk,
        startsAt: Carbon::parse('2026-01-10T09:00:00Z'),
        endsAt: Carbon::parse('2026-01-12T17:00:00Z'),
        isCritical: false,
        resolvedQuantity: $resolved,
    );
}

it('maps each shortage to its badge payload keyed by opportunity item id', function () {
    $collection = new ShortageCollection([
        shortage(itemId: 11, requested: 10, available: 4),        // shortfall 6
        shortage(itemId: 22, requested: 5, available: 1, resolved: 2), // shortfall 4, remaining 2
    ]);

    $badges = $collection->toBadges();

    expect($badges)->toHaveCount(2)
        ->and(array_keys($badges))->toBe([11, 22])
        ->and($badges[11]['shortfall'])->toBe(6)
        ->and($badges[11]['remaining_shortfall'])->toBe(6)
        ->and($badges[22]['shortfall'])->toBe(4)
        ->and($badges[22]['remaining_shortfall'])->toBe(2)
        ->and($badges[22]['opportunity_item_id'])->toBe(22);
});

it('returns an empty badge map for an empty collection', function () {
    expect((new ShortageCollection)->toBadges())->toBe([]);
});

it('reports unresolved shortages and filters down to only those still open', function () {
    $collection = new ShortageCollection([
        shortage(itemId: 11, requested: 10, available: 4),         // remaining 6 (open)
        shortage(itemId: 22, requested: 5, available: 1, resolved: 4), // remaining 0 (cleared)
    ]);

    expect($collection->hasUnresolved())->toBeTrue();

    $unresolved = $collection->unresolved();

    expect($unresolved)->toHaveCount(1)
        ->and($unresolved->first()->opportunityItemId)->toBe(11);
});

it('reports no unresolved shortage when every shortfall is covered', function () {
    $collection = new ShortageCollection([
        shortage(itemId: 11, requested: 10, available: 10), // shortfall 0
    ]);

    expect($collection->hasUnresolved())->toBeFalse()
        ->and($collection->unresolved())->toHaveCount(0);
});

it('builds a snapshot list, one entry per shortage', function () {
    $collection = new ShortageCollection([
        shortage(itemId: 11, requested: 10, available: 4),
    ]);

    $snapshots = $collection->toSnapshots();

    expect($snapshots)->toHaveCount(1)
        ->and($snapshots[0]['opportunity_item_id'])->toBe(11)
        ->and($snapshots[0])->toHaveKey('starts_at')
        ->and($snapshots[0]['store_id'])->toBe(1);
});
