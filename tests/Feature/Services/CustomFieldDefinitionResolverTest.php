<?php

use App\Models\CustomField;
use App\Services\CustomFieldDefinitionResolver;

it('clears cache for a specific module type', function () {
    CustomField::factory()->forModule('Member')->create(['name' => 'field_a']);
    CustomField::factory()->forModule('Invoice')->create(['name' => 'field_b']);

    $resolver = app(CustomFieldDefinitionResolver::class);

    // Populate the cache for both module types
    $memberFields = $resolver->resolve('Member');
    $invoiceFields = $resolver->resolve('Invoice');

    expect($memberFields)->toHaveCount(1)
        ->and($invoiceFields)->toHaveCount(1);

    // Add another Member field after cache was populated
    CustomField::factory()->forModule('Member')->create(['name' => 'field_c']);

    // Clear only Member cache
    $resolver->clearCache('Member');

    // Member should re-query and find 2 fields; Invoice should still be cached with 1
    $memberFieldsAfter = $resolver->resolve('Member');
    $invoiceFieldsAfter = $resolver->resolve('Invoice');

    expect($memberFieldsAfter)->toHaveCount(2)
        ->and($invoiceFieldsAfter)->toHaveCount(1);
});

it('clears all cache when no module type specified', function () {
    CustomField::factory()->forModule('Member')->create(['name' => 'field_x']);

    $resolver = app(CustomFieldDefinitionResolver::class);
    $resolver->resolve('Member');

    // Add another field
    CustomField::factory()->forModule('Member')->create(['name' => 'field_y']);

    // Clear all cache
    $resolver->clearCache();

    // Should re-query and find 2 fields
    expect($resolver->resolve('Member'))->toHaveCount(2);
});
