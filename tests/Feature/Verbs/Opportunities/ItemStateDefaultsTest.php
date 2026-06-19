<?php

use App\Enums\AssetAssignmentStatus;
use App\Enums\AssetCondition;
use App\Enums\ChargePeriod;
use App\Enums\LineItemTransactionType;
use App\Verbs\States\AssetAssignmentState;
use App\Verbs\States\OpportunityItemState;

/**
 * The item/asset Verbs states are inert scaffolding here — no events mutate them
 * until M3 (items) / M5 (assets). These tests pin their default property values
 * and enum accessors so those milestones build on a known baseline.
 */
it('instantiates an OpportunityItemState with expected defaults', function () {
    $state = new OpportunityItemState;

    expect($state->opportunity_item_id)->toBe(0)
        ->and($state->opportunity_id)->toBe(0)
        ->and($state->version_id)->toBeNull()
        ->and($state->item_id)->toBeNull()
        ->and($state->item_type)->toBeNull()
        ->and($state->quantity)->toBe('0')
        ->and($state->unit_price)->toBe(0)
        ->and($state->charge_period)->toBe(ChargePeriod::Day->value)
        ->and($state->total)->toBe(0)
        ->and($state->discount_percent)->toBeNull()
        ->and($state->transaction_type)->toBe(LineItemTransactionType::Rental->value)
        ->and($state->starts_at)->toBeNull()
        ->and($state->ends_at)->toBeNull()
        ->and($state->dispatched_quantity)->toBe('0')
        ->and($state->returned_quantity)->toBe('0')
        ->and($state->last_event_at)->toBeNull();
});

it('exposes type-safe enum accessors on OpportunityItemState', function () {
    $state = new OpportunityItemState;
    $state->charge_period = ChargePeriod::Week->value;
    $state->transaction_type = LineItemTransactionType::Service->value;

    expect($state->chargePeriod())->toBe(ChargePeriod::Week)
        ->and($state->transactionType())->toBe(LineItemTransactionType::Service);
});

it('instantiates an AssetAssignmentState with expected defaults', function () {
    $state = new AssetAssignmentState;

    expect($state->assignment_id)->toBe(0)
        ->and($state->opportunity_item_id)->toBe(0)
        ->and($state->stock_level_id)->toBeNull()
        ->and($state->status)->toBe(AssetAssignmentStatus::Allocated->value)
        ->and($state->container_stock_level_id)->toBeNull()
        ->and($state->allocated_at)->toBeNull()
        ->and($state->prepared_at)->toBeNull()
        ->and($state->dispatched_at)->toBeNull()
        ->and($state->returned_at)->toBeNull()
        ->and($state->checked_at)->toBeNull()
        ->and($state->condition_on_return)->toBeNull()
        ->and($state->last_event_at)->toBeNull();
});

it('exposes type-safe enum accessors on AssetAssignmentState', function () {
    $state = new AssetAssignmentState;

    expect($state->statusEnum())->toBe(AssetAssignmentStatus::Allocated)
        ->and($state->conditionOnReturn())->toBeNull();

    $state->status = AssetAssignmentStatus::Dispatched->value;
    $state->condition_on_return = AssetCondition::Good->value;

    expect($state->statusEnum())->toBe(AssetAssignmentStatus::Dispatched)
        ->and($state->conditionOnReturn())->toBe(AssetCondition::Good);
});
