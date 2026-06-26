<?php

use App\Enums\ShortageResolutionStatus;
use App\Enums\ShortageResolutionType;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\ShortageResolution;
use App\Models\ShortageResolutionItem;
use App\Models\User;

it('scopes active resolutions to non-terminal statuses', function () {
    $active = ShortageResolution::factory()->create(['status' => ShortageResolutionStatus::Confirmed->value]);
    $monitoring = ShortageResolution::factory()->monitoring()->create();
    $cancelled = ShortageResolution::factory()->cancelled()->create();
    ShortageResolution::factory()->create([
        'status' => ShortageResolutionStatus::Failed->value,
    ]);

    $ids = ShortageResolution::query()->active()->pluck('id')->all();

    expect($ids)->toContain($active->id, $monitoring->id)
        ->and($ids)->not->toContain($cancelled->id);
});

it('scopes resolutions to a specific opportunity item via the pivot', function () {
    $itemA = OpportunityItem::factory()->create();
    $itemB = OpportunityItem::factory()->create();

    $forA = ShortageResolution::factory()->create();
    ShortageResolutionItem::factory()->create([
        'shortage_resolution_id' => $forA->id,
        'opportunity_item_id' => $itemA->id,
    ]);

    $forB = ShortageResolution::factory()->create();
    ShortageResolutionItem::factory()->create([
        'shortage_resolution_id' => $forB->id,
        'opportunity_item_id' => $itemB->id,
    ]);

    expect(ShortageResolution::query()->forItem($itemA->id)->pluck('id')->all())
        ->toBe([$forA->id]);
});

it('scopes resolutions to any line item on an opportunity', function () {
    $opportunity = Opportunity::factory()->create();
    $item = OpportunityItem::factory()->for($opportunity)->create();
    $otherItem = OpportunityItem::factory()->create();

    $match = ShortageResolution::factory()->ofType('transfer', ShortageResolutionType::Transfer)->create();
    ShortageResolutionItem::factory()->create([
        'shortage_resolution_id' => $match->id,
        'opportunity_item_id' => $item->id,
    ]);

    $miss = ShortageResolution::factory()->create();
    ShortageResolutionItem::factory()->create([
        'shortage_resolution_id' => $miss->id,
        'opportunity_item_id' => $otherItem->id,
    ]);

    expect(ShortageResolution::query()->forOpportunity($opportunity->id)->pluck('id')->all())
        ->toBe([$match->id]);
});

it('relates resolver and confirmer users', function () {
    $resolverUser = User::factory()->create(['name' => 'Resolver Person']);
    $confirmerUser = User::factory()->create(['name' => 'Confirmer Person']);

    $resolution = ShortageResolution::factory()->create([
        'resolved_by' => $resolverUser->id,
        'confirmed_by' => $confirmerUser->id,
        'confirmed_at' => now(),
    ]);

    expect($resolution->resolver->name)->toBe('Resolver Person')
        ->and($resolution->confirmer->name)->toBe('Confirmer Person')
        ->and($resolution->items)->toBeIterable();
});

it('casts enums and metadata on the model', function () {
    $resolution = ShortageResolution::factory()->create([
        'resolution_type' => ShortageResolutionType::DateShift->value,
        'status' => ShortageResolutionStatus::Pending->value,
        'metadata' => ['shifted_starts_at' => '2026-07-01T00:00:00Z'],
    ]);

    expect($resolution->resolution_type)->toBe(ShortageResolutionType::DateShift)
        ->and($resolution->status)->toBe(ShortageResolutionStatus::Pending)
        ->and($resolution->metadata['shifted_starts_at'])->toBe('2026-07-01T00:00:00Z');
});
