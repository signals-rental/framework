<?php

use App\Models\ProductGroup;

it('creates a product group with factory', function () {
    $group = ProductGroup::factory()->create([
        'name' => 'Audio',
        'description' => 'Audio equipment',
        'sort_order' => 1,
    ]);

    expect($group->name)->toBe('Audio');
    expect($group->description)->toBe('Audio equipment');
    expect($group->sort_order)->toBe(1);
    expect($group)->toBeInstanceOf(ProductGroup::class);
});

it('casts sort_order to integer', function () {
    $group = ProductGroup::factory()->create(['sort_order' => 5]);

    expect($group->sort_order)->toBe(5)->toBeInt();
});

it('has a self-referential parent relationship', function () {
    $parent = ProductGroup::factory()->create(['name' => 'Lighting']);
    $child = ProductGroup::factory()->create([
        'name' => 'Moving Heads',
        'parent_id' => $parent->id,
    ]);

    expect($child->parent)->toBeInstanceOf(ProductGroup::class);
    expect($child->parent->id)->toBe($parent->id);
});

it('has a self-referential children relationship', function () {
    $parent = ProductGroup::factory()->create(['name' => 'Lighting']);
    ProductGroup::factory()->create(['name' => 'Generic', 'parent_id' => $parent->id]);
    ProductGroup::factory()->create(['name' => 'Moving Heads', 'parent_id' => $parent->id]);

    expect($parent->children)->toHaveCount(2);
    expect($parent->children->first())->toBeInstanceOf(ProductGroup::class);
});

it('creates a child group using withParent factory state', function () {
    $child = ProductGroup::factory()->withParent()->create();

    expect($child->parent_id)->not()->toBeNull();
    expect($child->parent)->toBeInstanceOf(ProductGroup::class);
});

it('scopes to root groups only', function () {
    $root1 = ProductGroup::factory()->create(['name' => 'Audio']);
    $root2 = ProductGroup::factory()->create(['name' => 'Video']);
    ProductGroup::factory()->create(['name' => 'Moving Heads', 'parent_id' => $root1->id]);

    $roots = ProductGroup::query()->roots()->get();

    expect($roots)->toHaveCount(2);
    expect($roots->pluck('name')->toArray())->toContain('Audio', 'Video');
});

it('scopes to ordered by sort_order', function () {
    ProductGroup::factory()->create(['name' => 'Video', 'sort_order' => 3]);
    ProductGroup::factory()->create(['name' => 'Audio', 'sort_order' => 1]);
    ProductGroup::factory()->create(['name' => 'Lighting', 'sort_order' => 2]);

    $ordered = ProductGroup::query()->ordered()->get();

    expect($ordered->first()->name)->toBe('Audio');
    expect($ordered->last()->name)->toBe('Video');
});

it('defaults parent_id to null for root groups', function () {
    $group = ProductGroup::factory()->create();

    expect($group->parent_id)->toBeNull();
});

it('has many products', function () {
    $group = ProductGroup::factory()->create();
    \App\Models\Product::factory()->count(3)->create(['product_group_id' => $group->id]);

    expect($group->products)->toHaveCount(3);
    expect($group->products->first())->toBeInstanceOf(\App\Models\Product::class);
});

it('defines a schema', function () {
    $builder = new \App\Services\SchemaBuilder;
    ProductGroup::defineSchema($builder);

    $fields = $builder->build();

    expect($fields)->toHaveKey('name');
    expect($fields)->toHaveKey('description');
    expect($fields)->toHaveKey('parent_id');
    expect($fields)->toHaveKey('sort_order');
    expect($fields)->toHaveKey('created_at');
});
