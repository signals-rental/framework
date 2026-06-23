<?php

use App\Enums\OpportunityItemType;

it('exposes the four RMS line-item roles', function () {
    expect(OpportunityItemType::Group->value)->toBe('group')
        ->and(OpportunityItemType::Product->value)->toBe('product')
        ->and(OpportunityItemType::Accessory->value)->toBe('accessory')
        ->and(OpportunityItemType::Service->value)->toBe('service');
});

it('marks only non-group roles priceable and only product/accessory demand-generating', function () {
    expect(OpportunityItemType::Group->isPriceable())->toBeFalse()
        ->and(OpportunityItemType::Product->isPriceable())->toBeTrue()
        ->and(OpportunityItemType::Group->generatesDemand())->toBeFalse()
        ->and(OpportunityItemType::Accessory->generatesDemand())->toBeTrue()
        ->and(OpportunityItemType::Service->generatesDemand())->toBeFalse();
});
