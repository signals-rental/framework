<?php

use App\Enums\ShortageResolutionStatus;

describe('ShortageResolutionStatus §8.3 transition matrix', function () {
    it('permits the documented forward transitions', function (ShortageResolutionStatus $from, ShortageResolutionStatus $to) {
        expect($from->canTransitionTo($to))->toBeTrue();
    })->with([
        'pending → confirmed' => [ShortageResolutionStatus::Pending, ShortageResolutionStatus::Confirmed],
        'pending → cancelled' => [ShortageResolutionStatus::Pending, ShortageResolutionStatus::Cancelled],
        'pending → failed' => [ShortageResolutionStatus::Pending, ShortageResolutionStatus::Failed],
        'monitoring → confirmed' => [ShortageResolutionStatus::Monitoring, ShortageResolutionStatus::Confirmed],
        'monitoring → cancelled' => [ShortageResolutionStatus::Monitoring, ShortageResolutionStatus::Cancelled],
        'monitoring → failed' => [ShortageResolutionStatus::Monitoring, ShortageResolutionStatus::Failed],
        'confirmed → in_progress' => [ShortageResolutionStatus::Confirmed, ShortageResolutionStatus::InProgress],
        'confirmed → cancelled' => [ShortageResolutionStatus::Confirmed, ShortageResolutionStatus::Cancelled],
        'in_progress → fulfilled' => [ShortageResolutionStatus::InProgress, ShortageResolutionStatus::Fulfilled],
        'in_progress → partially_fulfilled' => [ShortageResolutionStatus::InProgress, ShortageResolutionStatus::PartiallyFulfilled],
        'partially_fulfilled → fulfilled' => [ShortageResolutionStatus::PartiallyFulfilled, ShortageResolutionStatus::Fulfilled],
    ]);

    it('rejects illegal transitions', function (ShortageResolutionStatus $from, ShortageResolutionStatus $to) {
        expect($from->canTransitionTo($to))->toBeFalse();
    })->with([
        'pending → in_progress (skips confirm)' => [ShortageResolutionStatus::Pending, ShortageResolutionStatus::InProgress],
        'pending → fulfilled' => [ShortageResolutionStatus::Pending, ShortageResolutionStatus::Fulfilled],
        'confirmed → fulfilled (skips in_progress)' => [ShortageResolutionStatus::Confirmed, ShortageResolutionStatus::Fulfilled],
        'confirmed → failed' => [ShortageResolutionStatus::Confirmed, ShortageResolutionStatus::Failed],
        'in_progress → cancelled' => [ShortageResolutionStatus::InProgress, ShortageResolutionStatus::Cancelled],
        'cancelled → confirmed' => [ShortageResolutionStatus::Cancelled, ShortageResolutionStatus::Confirmed],
        'failed → confirmed' => [ShortageResolutionStatus::Failed, ShortageResolutionStatus::Confirmed],
        'fulfilled → in_progress' => [ShortageResolutionStatus::Fulfilled, ShortageResolutionStatus::InProgress],
    ]);

    it('treats fulfilled, cancelled and failed as terminal', function () {
        expect(ShortageResolutionStatus::Fulfilled->isTerminal())->toBeTrue()
            ->and(ShortageResolutionStatus::Cancelled->isTerminal())->toBeTrue()
            ->and(ShortageResolutionStatus::Failed->isTerminal())->toBeTrue()
            ->and(ShortageResolutionStatus::Pending->isTerminal())->toBeFalse()
            ->and(ShortageResolutionStatus::Confirmed->isTerminal())->toBeFalse();

        expect(ShortageResolutionStatus::Fulfilled->allowedTransitions())->toBe([])
            ->and(ShortageResolutionStatus::Cancelled->allowedTransitions())->toBe([])
            ->and(ShortageResolutionStatus::Failed->allowedTransitions())->toBe([]);
    });
});
