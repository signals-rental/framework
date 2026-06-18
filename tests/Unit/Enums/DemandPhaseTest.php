<?php

use App\Enums\DemandPhase;

it('backs each phase with the expected string value', function () {
    expect(DemandPhase::Draft->value)->toBe('draft')
        ->and(DemandPhase::Committed->value)->toBe('committed')
        ->and(DemandPhase::Operational->value)->toBe('operational')
        ->and(DemandPhase::Closed->value)->toBe('closed')
        ->and(DemandPhase::Void->value)->toBe('void');
});

it('marks only committed and operational phases active', function (DemandPhase $phase, bool $active) {
    expect($phase->isActive())->toBe($active);
})->with([
    'draft is inactive' => [DemandPhase::Draft, false],
    'committed is active' => [DemandPhase::Committed, true],
    'operational is active' => [DemandPhase::Operational, true],
    'closed is inactive' => [DemandPhase::Closed, false],
    'void is inactive' => [DemandPhase::Void, false],
]);

it('applies a turnaround buffer only for phases that physically occupy stock', function (DemandPhase $phase, bool $applies) {
    expect($phase->appliesTurnaround())->toBe($applies);
})->with([
    'draft applies no turnaround' => [DemandPhase::Draft, false],
    'committed applies turnaround' => [DemandPhase::Committed, true],
    'operational applies turnaround' => [DemandPhase::Operational, true],
    'closed applies turnaround' => [DemandPhase::Closed, true],
    'void applies no turnaround' => [DemandPhase::Void, false],
]);

it('exposes a human label for each phase', function () {
    expect(DemandPhase::Draft->label())->toBe('Draft')
        ->and(DemandPhase::Committed->label())->toBe('Committed')
        ->and(DemandPhase::Operational->label())->toBe('Operational')
        ->and(DemandPhase::Closed->label())->toBe('Closed')
        ->and(DemandPhase::Void->label())->toBe('Void');
});
