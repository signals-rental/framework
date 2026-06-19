<?php

use App\Enums\DemandPhase;
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

it('maps statuses to their demand phase', function (OpportunityStatus $status, DemandPhase $phase) {
    expect($status->phase())->toBe($phase);
})->with([
    'draft → draft' => [OpportunityStatus::DraftOpen, DemandPhase::Draft],
    'provisional → draft' => [OpportunityStatus::QuotationProvisional, DemandPhase::Draft],
    'postponed → held' => [OpportunityStatus::QuotationPostponed, DemandPhase::Held],
    'reserved → committed' => [OpportunityStatus::QuotationReserved, DemandPhase::Committed],
    'active → committed' => [OpportunityStatus::OrderActive, DemandPhase::Committed],
    'dispatched → operational' => [OpportunityStatus::OrderDispatched, DemandPhase::Operational],
    'on hire → operational' => [OpportunityStatus::OrderOnHire, DemandPhase::Operational],
    'returned → closed' => [OpportunityStatus::OrderReturned, DemandPhase::Closed],
    'checked → closed' => [OpportunityStatus::OrderChecked, DemandPhase::Closed],
    'complete → closed' => [OpportunityStatus::OrderComplete, DemandPhase::Closed],
    'cancelled → void' => [OpportunityStatus::OrderCancelled, DemandPhase::Void],
    'lost → void' => [OpportunityStatus::QuotationLost, DemandPhase::Void],
    'dead → void' => [OpportunityStatus::QuotationDead, DemandPhase::Void],
]);

it('reports which demand phases are active', function () {
    expect(DemandPhase::Committed->isActive())->toBeTrue()
        ->and(DemandPhase::Operational->isActive())->toBeTrue()
        ->and(DemandPhase::Draft->isActive())->toBeFalse()
        ->and(DemandPhase::Closed->isActive())->toBeFalse()
        ->and(DemandPhase::Void->isActive())->toBeFalse();
});

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
