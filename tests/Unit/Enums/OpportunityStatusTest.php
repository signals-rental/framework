<?php

use App\Enums\AvailabilityPhase;
use App\Enums\OpportunityState;
use App\Enums\OpportunityStatus;

it('maps each status to its owning state', function (OpportunityStatus $status, OpportunityState $state) {
    expect($status->state())->toBe($state);
})->with([
    'draft open' => [OpportunityStatus::DraftOpen, OpportunityState::Draft],
    'quotation provisional' => [OpportunityStatus::QuotationProvisional, OpportunityState::Quotation],
    'quotation reserved' => [OpportunityStatus::QuotationReserved, OpportunityState::Quotation],
    'order active' => [OpportunityStatus::OrderActive, OpportunityState::Order],
    'order on hire' => [OpportunityStatus::OrderOnHire, OpportunityState::Order],
]);

it('exposes the per-state integer stored in the projection', function () {
    expect(OpportunityStatus::DraftOpen->statusValue())->toBe(0)
        ->and(OpportunityStatus::QuotationProvisional->statusValue())->toBe(0)
        ->and(OpportunityStatus::QuotationReserved->statusValue())->toBe(1)
        ->and(OpportunityStatus::OrderActive->statusValue())->toBe(0)
        ->and(OpportunityStatus::OrderCancelled->statusValue())->toBe(6);
});

it('rebuilds a status from the two persisted columns', function () {
    expect(OpportunityStatus::fromStateAndStatus(OpportunityState::Quotation, 1))
        ->toBe(OpportunityStatus::QuotationReserved)
        ->and(OpportunityStatus::fromStateAndStatus(OpportunityState::Order, 0))
        ->toBe(OpportunityStatus::OrderActive);
});

it('maps statuses to their availability phase', function (OpportunityStatus $status, AvailabilityPhase $phase) {
    expect($status->phase())->toBe($phase);
})->with([
    'draft → none' => [OpportunityStatus::DraftOpen, AvailabilityPhase::None],
    'provisional → none' => [OpportunityStatus::QuotationProvisional, AvailabilityPhase::None],
    'reserved → soft' => [OpportunityStatus::QuotationReserved, AvailabilityPhase::Soft],
    'active → confirmed' => [OpportunityStatus::OrderActive, AvailabilityPhase::Confirmed],
    'dispatched → on hire' => [OpportunityStatus::OrderDispatched, AvailabilityPhase::OnHire],
    'on hire → on hire' => [OpportunityStatus::OrderOnHire, AvailabilityPhase::OnHire],
    'lost → none' => [OpportunityStatus::QuotationLost, AvailabilityPhase::None],
]);

it('reports closed statuses', function () {
    expect(OpportunityStatus::QuotationLost->isClosed())->toBeTrue()
        ->and(OpportunityStatus::QuotationDead->isClosed())->toBeTrue()
        ->and(OpportunityStatus::OrderComplete->isClosed())->toBeTrue()
        ->and(OpportunityStatus::OrderCancelled->isClosed())->toBeTrue()
        ->and(OpportunityStatus::DraftOpen->isClosed())->toBeFalse()
        ->and(OpportunityStatus::OrderActive->isClosed())->toBeFalse();
});

it('resolves the default open status for each state', function () {
    expect(OpportunityState::Draft->defaultStatus())->toBe(OpportunityStatus::DraftOpen)
        ->and(OpportunityState::Quotation->defaultStatus())->toBe(OpportunityStatus::QuotationProvisional)
        ->and(OpportunityState::Order->defaultStatus())->toBe(OpportunityStatus::OrderActive);
});
