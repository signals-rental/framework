<?php

use App\Models\ListName;
use App\Models\ListValue;

it('belongs to a list name', function () {
    $listName = ListName::factory()->create();
    $value = ListValue::factory()->for($listName)->create();

    expect($value->listName->id)->toBe($listName->id);
});

it('scopes to active values', function () {
    $listName = ListName::factory()->create();
    ListValue::factory()->for($listName)->create(['is_active' => true]);
    ListValue::factory()->for($listName)->create(['is_active' => false]);

    expect(ListValue::active()->count())->toBe(1);
});

it('supports parent-child hierarchy', function () {
    $listName = ListName::factory()->create();
    $parent = ListValue::factory()->for($listName)->create();
    $child = ListValue::factory()->for($listName)->create(['parent_id' => $parent->id]);

    expect($parent->children)->toHaveCount(1)
        ->and($parent->children->first()->id)->toBe($child->id)
        ->and($child->parent->id)->toBe($parent->id);
});

it('casts metadata to array', function () {
    $value = ListValue::factory()->create(['metadata' => ['key' => 'val']]);

    expect($value->metadata)->toBe(['key' => 'val']);
});
