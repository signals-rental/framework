<?php

use App\Enums\AssetCondition;
use App\Enums\ChargePeriod;
use App\Enums\ContainerStatus;
use App\Enums\ShortageResolutionType;

describe('AssetCondition::label', function () {
    it('labels every condition', function () {
        expect(AssetCondition::Good->label())->toBe('Good')
            ->and(AssetCondition::Damaged->label())->toBe('Damaged')
            ->and(AssetCondition::Missing->label())->toBe('Missing');
    });
});

describe('ChargePeriod::label', function () {
    it('labels every charge period', function () {
        expect(ChargePeriod::Hour->label())->toBe('Hour')
            ->and(ChargePeriod::Day->label())->toBe('Day')
            ->and(ChargePeriod::Week->label())->toBe('Week')
            ->and(ChargePeriod::Month->label())->toBe('Month')
            ->and(ChargePeriod::Fixed->label())->toBe('Fixed');
    });
});

describe('ContainerStatus', function () {
    it('labels every status', function () {
        expect(ContainerStatus::Open->label())->toBe('Open')
            ->and(ContainerStatus::Sealed->label())->toBe('Sealed')
            ->and(ContainerStatus::Dispatched->label())->toBe('Dispatched')
            ->and(ContainerStatus::Returned->label())->toBe('Returned')
            ->and(ContainerStatus::Dissolved->label())->toBe('Dissolved');
    });

    it('holds contents for every status except Dissolved', function () {
        expect(ContainerStatus::Open->holdsContents())->toBeTrue()
            ->and(ContainerStatus::Sealed->holdsContents())->toBeTrue()
            ->and(ContainerStatus::Dispatched->holdsContents())->toBeTrue()
            ->and(ContainerStatus::Returned->holdsContents())->toBeTrue()
            ->and(ContainerStatus::Dissolved->holdsContents())->toBeFalse();
    });

    it('accepts packing only when Open', function () {
        expect(ContainerStatus::Open->acceptsPacking())->toBeTrue()
            ->and(ContainerStatus::Sealed->acceptsPacking())->toBeFalse()
            ->and(ContainerStatus::Dispatched->acceptsPacking())->toBeFalse();
    });
});

describe('ShortageResolutionType::label', function () {
    it('labels every resolution type', function () {
        expect(ShortageResolutionType::Reallocate->label())->toBe('Reallocate from quote')
            ->and(ShortageResolutionType::Substitute->label())->toBe('Substitute product')
            ->and(ShortageResolutionType::Transfer->label())->toBe('Warehouse transfer')
            ->and(ShortageResolutionType::DateShift->label())->toBe('Shift dates')
            ->and(ShortageResolutionType::Partial->label())->toBe('Partial fulfilment')
            ->and(ShortageResolutionType::Waitlist->label())->toBe('Waitlist')
            ->and(ShortageResolutionType::Subhire->label())->toBe('Sub-hire')
            ->and(ShortageResolutionType::Purchase->label())->toBe('Purchase')
            ->and(ShortageResolutionType::Custom->label())->toBe('Custom');
    });
});
