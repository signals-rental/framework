<?php

use App\Models\Member;
use App\Models\Product;
use App\Services\CustomFieldModuleRegistry;

it('lists the module-type string keys only', function () {
    $types = app(CustomFieldModuleRegistry::class)->moduleTypes();

    expect($types)->toBeArray()
        ->and($types)->toContain('Member')
        ->and($types)->toContain('Product')
        ->and($types)->toContain('Opportunity')
        // Keys are the class basenames, not the human-readable labels.
        ->and($types)->not->toContain('Product Group')
        ->and($types)->toContain('ProductGroup');
});

it('returns the human-readable label for a registered module type', function () {
    $registry = app(CustomFieldModuleRegistry::class);

    expect($registry->label('Member'))->toBe('Member')
        ->and($registry->label('ProductGroup'))->toBe('Product Group')
        ->and($registry->label('StockLevel'))->toBe('Stock Level');
});

it('returns the module-type string itself when it is not registered', function () {
    expect(app(CustomFieldModuleRegistry::class)->label('NotAModule'))->toBe('NotAModule');
});

it('exposes the backing model class-strings', function () {
    $models = app(CustomFieldModuleRegistry::class)->models();

    expect($models)->toContain(Member::class)
        ->and($models)->toContain(Product::class);
});
