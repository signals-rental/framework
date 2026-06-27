<?php

use App\Models\CustomView;
use App\Services\ViewResolver;

it('resolves a null custom-field module for a model that does not support custom fields', function () {
    // CustomView itself does NOT use HasCustomFields, so resolveCustomFieldModule
    // takes the `return null` branch. Driving applyFilters with an explicit param
    // on a CustomView query exercises that path without crashing.
    $view = CustomView::factory()->create([
        'entity_type' => 'members',
        'filters' => [
            ['field' => 'entity_type', 'predicate' => 'eq', 'value' => 'members'],
        ],
    ]);

    $query = CustomView::query();

    // No customFieldModuleType() on CustomView => module resolves to null; the
    // filter still applies cleanly.
    app(ViewResolver::class)->applyFilters($query, $view, ['entity_type_eq' => 'members'], ['entity_type']);

    expect($query->toSql())->toContain('where');
});
