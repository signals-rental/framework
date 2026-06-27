<?php

use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Store;
use App\Models\User;
use App\Services\Opportunities\OpportunityItemChargeableDays;
use App\Services\Opportunities\OpportunityItemChargeBounds;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

it('rejects a unit price above the signed 32-bit minor-unit ceiling', function () {
    $opportunity = Opportunity::factory()->create();
    $item = OpportunityItem::factory()->create([
        'opportunity_id' => $opportunity->id,
        'quantity' => '1',
    ]);

    $bounds = app(OpportunityItemChargeBounds::class);

    expect(fn () => $bounds->assertUnitPriceAndProjectedTotalFit(
        $item,
        $opportunity,
        OpportunityItemChargeBounds::MAX_MINOR + 1,
    ))->toThrow(ValidationException::class, 'The unit price exceeds the maximum allowed value.');

    // A negative unit price is rejected the same way.
    expect(fn () => $bounds->assertUnitPriceAndProjectedTotalFit($item, $opportunity, -1))
        ->toThrow(ValidationException::class, 'The unit price exceeds the maximum allowed value.');
});

it('uses the opportunity chargeable_days override for the day count', function () {
    $opportunity = Opportunity::factory()->create([
        'use_chargeable_days' => true,
        'chargeable_days' => 7,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-02T09:00:00Z',
    ]);
    $item = OpportunityItem::factory()->create([
        'opportunity_id' => $opportunity->id,
        'quantity' => '1',
    ]);

    // 7-day override wins over the 1-day window.
    expect(app(OpportunityItemChargeableDays::class)->forItem($item, $opportunity))->toBe(7);
});

it('lazy-loads the opportunity when not passed and the relation is unloaded', function () {
    $opportunity = Opportunity::factory()->create([
        'use_chargeable_days' => true,
        'chargeable_days' => 5,
    ]);
    $item = OpportunityItem::factory()->create([
        'opportunity_id' => $opportunity->id,
        'quantity' => '1',
    ]);

    // Re-fetch the item fresh so the opportunity relation is NOT loaded; the
    // service must resolve it via the relation query (lines 18-19, 22).
    $fresh = OpportunityItem::query()->whereKey($item->id)->firstOrFail();

    expect($fresh->relationLoaded('opportunity'))->toBeFalse()
        ->and(app(OpportunityItemChargeableDays::class)->forItem($fresh))->toBe(5);
});

it('uses the already-loaded opportunity relation when no opportunity is passed', function () {
    $opportunity = Opportunity::factory()->create([
        'use_chargeable_days' => true,
        'chargeable_days' => 3,
    ]);
    $item = OpportunityItem::factory()->create([
        'opportunity_id' => $opportunity->id,
        'quantity' => '1',
    ]);

    // Eager-load the relation so the resolver reads $item->opportunity directly
    // (the relationLoaded === true branch on line 18).
    $loaded = OpportunityItem::query()->with('opportunity')->whereKey($item->id)->firstOrFail();

    expect($loaded->relationLoaded('opportunity'))->toBeTrue()
        ->and(app(OpportunityItemChargeableDays::class)->forItem($loaded))->toBe(3);
});

it('counts whole days between item dates when no chargeable-days override applies', function () {
    $opportunity = Opportunity::factory()->create([
        'use_chargeable_days' => false,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-05T09:00:00Z',
    ]);
    $item = OpportunityItem::factory()->create([
        'opportunity_id' => $opportunity->id,
        'quantity' => '1',
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-04T09:00:00Z',
    ]);

    expect(app(OpportunityItemChargeableDays::class)->forItem($item, $opportunity))->toBe(3);
});

it('falls back to one day when neither item nor opportunity carry a window', function () {
    $opportunity = Opportunity::factory()->create([
        'use_chargeable_days' => false,
        'starts_at' => null,
        'ends_at' => null,
    ]);
    $item = OpportunityItem::factory()->create([
        'opportunity_id' => $opportunity->id,
        'quantity' => '1',
        'starts_at' => null,
        'ends_at' => null,
    ]);

    expect(app(OpportunityItemChargeableDays::class)->forItem($item, $opportunity))->toBe(1);
});
