<?php

use App\Enums\ShortageResolutionStatus;

describe('ShortageResolutionStatus::isActive', function () {
    it('treats every status except Cancelled and Failed as active', function () {
        expect(ShortageResolutionStatus::Pending->isActive())->toBeTrue()
            ->and(ShortageResolutionStatus::Confirmed->isActive())->toBeTrue()
            ->and(ShortageResolutionStatus::Monitoring->isActive())->toBeTrue()
            ->and(ShortageResolutionStatus::InProgress->isActive())->toBeTrue()
            ->and(ShortageResolutionStatus::Fulfilled->isActive())->toBeTrue()
            ->and(ShortageResolutionStatus::PartiallyFulfilled->isActive())->toBeTrue();
    });

    it('treats Cancelled and Failed as inactive', function () {
        expect(ShortageResolutionStatus::Cancelled->isActive())->toBeFalse()
            ->and(ShortageResolutionStatus::Failed->isActive())->toBeFalse();
    });

    it('labels every status', function () {
        expect(ShortageResolutionStatus::Pending->label())->toBe('Pending')
            ->and(ShortageResolutionStatus::Confirmed->label())->toBe('Confirmed')
            ->and(ShortageResolutionStatus::Monitoring->label())->toBe('Monitoring')
            ->and(ShortageResolutionStatus::InProgress->label())->toBe('In progress')
            ->and(ShortageResolutionStatus::Fulfilled->label())->toBe('Fulfilled')
            ->and(ShortageResolutionStatus::PartiallyFulfilled->label())->toBe('Partially fulfilled')
            ->and(ShortageResolutionStatus::Cancelled->label())->toBe('Cancelled')
            ->and(ShortageResolutionStatus::Failed->label())->toBe('Failed');
    });
});
