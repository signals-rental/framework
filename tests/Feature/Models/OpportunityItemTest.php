<?php

use App\Enums\ChargePeriod;
use App\Enums\LineItemTransactionType;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;

it('belongs to an opportunity', function () {
    $opportunity = Opportunity::factory()->create();
    $item = OpportunityItem::factory()->for($opportunity)->create();

    expect($item->opportunity)->toBeInstanceOf(Opportunity::class)
        ->and($item->opportunity->id)->toBe($opportunity->id);
});

it('exposes its items via the inverse relation in sort order', function () {
    $opportunity = Opportunity::factory()->create();
    OpportunityItem::factory()->for($opportunity)->create(['sort_order' => 2, 'name' => 'Second']);
    OpportunityItem::factory()->for($opportunity)->create(['sort_order' => 1, 'name' => 'First']);

    $items = $opportunity->items()->get();

    expect($items)->toHaveCount(2)
        ->and($items->first()->name)->toBe('First')
        ->and($items->last()->name)->toBe('Second');
});

it('has many assets', function () {
    $item = OpportunityItem::factory()->create();
    OpportunityItemAsset::factory()->count(3)->for($item, 'item')->create();

    expect($item->assets)->toHaveCount(3)
        ->and($item->assets->first())->toBeInstanceOf(OpportunityItemAsset::class);
});

it('uses an application-assigned non-incrementing integer primary key', function () {
    $item = new OpportunityItem;

    expect($item->getIncrementing())->toBeFalse()
        ->and($item->getKeyType())->toBe('int');

    // Explicit-id inserts must persist verbatim (replay-stable PK).
    $created = OpportunityItem::factory()->create(['id' => 4242]);
    expect($created->id)->toBe(4242);
    $this->assertDatabaseHas('opportunity_items', ['id' => 4242]);
});

it('casts money columns as integer minor units and round-trips them', function () {
    $item = OpportunityItem::factory()->create([
        'unit_price' => 7500,
        'total' => 15000,
    ]);

    $fresh = $item->fresh();

    expect($fresh->unit_price)->toBe(7500)
        ->and($fresh->total)->toBe(15000)
        // formatMoneyCost converts minor units to a decimal string.
        ->and($fresh->formatMoneyCost('unit_price'))->toBe('75.00')
        ->and($fresh->formatMoneyCost('total'))->toBe('150.00');
});

it('casts charge_period and transaction_type to enums', function () {
    $item = OpportunityItem::factory()->create([
        'charge_period' => ChargePeriod::Week->value,
        'transaction_type' => LineItemTransactionType::SubRental->value,
    ]);

    $fresh = $item->fresh();

    expect($fresh->charge_period)->toBe(ChargePeriod::Week)
        ->and($fresh->transaction_type)->toBe(LineItemTransactionType::SubRental);
});

it('casts decimal quantity, custom_fields json, and the optional flag', function () {
    $item = OpportunityItem::factory()->create([
        'quantity' => 3,
        'is_optional' => true,
        'custom_fields' => ['rigging_height' => '12m'],
    ]);

    $fresh = $item->fresh();

    expect($fresh->quantity)->toBe('3.00')
        ->and($fresh->is_optional)->toBeTrue()
        ->and($fresh->custom_fields)->toBe(['rigging_height' => '12m']);
});

it('cascade-deletes its assets when the item is removed', function () {
    $item = OpportunityItem::factory()->create();
    $asset = OpportunityItemAsset::factory()->for($item, 'item')->create();

    $item->delete();

    $this->assertDatabaseMissing('opportunity_item_assets', ['id' => $asset->id]);
});
