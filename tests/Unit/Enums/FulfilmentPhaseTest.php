<?php

use App\Enums\FulfilmentPhase;
use App\Enums\OpportunityStatus;

it('backs each phase with the expected string value', function () {
    expect(FulfilmentPhase::NotStarted->value)->toBe('not_started')
        ->and(FulfilmentPhase::PendingDispatch->value)->toBe('pending_dispatch')
        ->and(FulfilmentPhase::OnHire->value)->toBe('on_hire')
        ->and(FulfilmentPhase::Returned->value)->toBe('returned')
        ->and(FulfilmentPhase::Checked->value)->toBe('checked');
});

it('maps each fulfilment phase to the concrete order status it promotes to', function (FulfilmentPhase $phase, ?OpportunityStatus $status) {
    expect($phase->toStatus())->toBe($status);
})->with([
    // NotStarted implies NO promotion — the order stays Active.
    'not started → no promotion' => [FulfilmentPhase::NotStarted, null],
    'pending dispatch → dispatched' => [FulfilmentPhase::PendingDispatch, OpportunityStatus::OrderDispatched],
    'on hire → on hire' => [FulfilmentPhase::OnHire, OpportunityStatus::OrderOnHire],
    'returned → returned' => [FulfilmentPhase::Returned, OpportunityStatus::OrderReturned],
    'checked → checked' => [FulfilmentPhase::Checked, OpportunityStatus::OrderChecked],
]);

it('preserves the exact statuses the legacy tally cascade produced', function () {
    // Mirrors the original deriveOrderStatus() if-cascade: !anyDispatched → null;
    // hasUndispatched → Dispatched; hasUnreturned → OnHire; hasUncheckedReturn →
    // Returned; else → Checked. The phase axis must reproduce each branch verbatim.
    expect(FulfilmentPhase::NotStarted->toStatus())->toBeNull()
        ->and(FulfilmentPhase::PendingDispatch->toStatus())->toBe(OpportunityStatus::OrderDispatched)
        ->and(FulfilmentPhase::OnHire->toStatus())->toBe(OpportunityStatus::OrderOnHire)
        ->and(FulfilmentPhase::Returned->toStatus())->toBe(OpportunityStatus::OrderReturned)
        ->and(FulfilmentPhase::Checked->toStatus())->toBe(OpportunityStatus::OrderChecked);
});

it('only NotStarted maps to a null (no-promotion) status', function () {
    $nullPhases = array_values(array_filter(
        FulfilmentPhase::cases(),
        fn (FulfilmentPhase $phase): bool => $phase->toStatus() === null,
    ));

    expect($nullPhases)->toBe([FulfilmentPhase::NotStarted]);
});

it('exposes a human label for each phase', function () {
    expect(FulfilmentPhase::NotStarted->label())->toBe('Not Started')
        ->and(FulfilmentPhase::PendingDispatch->label())->toBe('Pending Dispatch')
        ->and(FulfilmentPhase::OnHire->label())->toBe('On Hire')
        ->and(FulfilmentPhase::Returned->label())->toBe('Returned')
        ->and(FulfilmentPhase::Checked->label())->toBe('Checked');
});
