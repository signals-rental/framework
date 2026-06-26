<?php

use App\Enums\OpportunityItemType;

it('exposes the RMS line-item roles including text', function () {
    expect(OpportunityItemType::Group->value)->toBe('group')
        ->and(OpportunityItemType::Product->value)->toBe('product')
        ->and(OpportunityItemType::Accessory->value)->toBe('accessory')
        ->and(OpportunityItemType::Service->value)->toBe('service')
        ->and(OpportunityItemType::Text->value)->toBe('text');
});

it('marks only group as non-priceable and only product/accessory demand-generating', function () {
    expect(OpportunityItemType::Group->isPriceable())->toBeFalse()
        ->and(OpportunityItemType::Text->isPriceable())->toBeTrue()
        ->and(OpportunityItemType::Product->isPriceable())->toBeTrue()
        ->and(OpportunityItemType::Service->isPriceable())->toBeTrue()
        ->and(OpportunityItemType::Group->generatesDemand())->toBeFalse()
        ->and(OpportunityItemType::Text->generatesDemand())->toBeFalse()
        ->and(OpportunityItemType::Accessory->generatesDemand())->toBeTrue()
        ->and(OpportunityItemType::Service->generatesDemand())->toBeFalse()
        ->and(OpportunityItemType::Text->label())->toBe('Free text item');
});
