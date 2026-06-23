<?php

use App\Enums\OpportunityItemType;
use App\Verbs\States\OpportunityItemState;

/**
 * Asserts that OpportunityItemState carries the unified Current-RMS field set
 * introduced in the P1-T3 re-platform step:
 *
 *  - `itemable_type` / `itemable_id`  — renamed polymorphic catalogue reference
 *  - `item_type` (OpportunityItemType) — structural role (group/product/etc.)
 *  - `path`                            — dot-notation tree position
 *  - `revenue_group_id`                — revenue group FK
 *  - NO `section_id`                   — never belonged here; sections replaced
 */
it('has the expected unified-model properties on OpportunityItemState', function () {
    expect(property_exists(OpportunityItemState::class, 'itemable_id'))->toBeTrue()
        ->and(property_exists(OpportunityItemState::class, 'itemable_type'))->toBeTrue()
        ->and(property_exists(OpportunityItemState::class, 'item_type'))->toBeTrue()
        ->and(property_exists(OpportunityItemState::class, 'path'))->toBeTrue()
        ->and(property_exists(OpportunityItemState::class, 'revenue_group_id'))->toBeTrue();
});

it('does not carry section_id on OpportunityItemState', function () {
    expect(property_exists(OpportunityItemState::class, 'section_id'))->toBeFalse();
});

it('defaults path to empty string and item_type to OpportunityItemType::Product', function () {
    $state = new OpportunityItemState;

    expect($state->path)->toBe('')
        ->and($state->item_type)->toBe(OpportunityItemType::Product);
});

it('defaults itemable_id and itemable_type to null', function () {
    $state = new OpportunityItemState;

    expect($state->itemable_id)->toBeNull()
        ->and($state->itemable_type)->toBeNull();
});

it('defaults revenue_group_id to null', function () {
    $state = new OpportunityItemState;

    expect($state->revenue_group_id)->toBeNull();
});
