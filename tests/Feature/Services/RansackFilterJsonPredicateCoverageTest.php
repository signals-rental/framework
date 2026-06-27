<?php

use App\Models\Product;
use App\Services\Api\RansackFilter;

beforeEach(function () {
    $this->filter = app(RansackFilter::class);
    // Product::tag_list is cast to `array`, so it routes through applyJsonPredicate.
    $this->allowed = ['tag_list'];
});

/**
 * Three products with distinct tag arrays plus one empty-tag product, returning
 * the IDs so membership predicates can be asserted exactly.
 *
 * @return array{red: int, green: int, blue: int, empty: int}
 */
function jsonTagProducts(): array
{
    return [
        'red' => Product::factory()->create(['tag_list' => ['red', 'hot']])->id,
        'green' => Product::factory()->create(['tag_list' => ['green']])->id,
        'blue' => Product::factory()->create(['tag_list' => ['blue', 'cool']])->id,
        'empty' => Product::factory()->create(['tag_list' => []])->id,
    ];
}

it('matches array membership with the cont/eq predicate', function () {
    $ids = jsonTagProducts();

    $matched = $this->filter->apply(Product::query(), ['tag_list_eq' => 'red'], $this->allowed)
        ->whereKey([$ids['red'], $ids['green'], $ids['blue'], $ids['empty']])
        ->pluck('id');

    expect($matched->all())->toBe([$ids['red']]);
});

it('excludes array membership with the not_cont/not_eq predicate', function () {
    $ids = jsonTagProducts();

    $matched = $this->filter->apply(Product::query(), ['tag_list_not_cont' => 'red'], $this->allowed)
        ->whereKey([$ids['red'], $ids['green'], $ids['blue'], $ids['empty']])
        ->pluck('id')->all();

    expect($matched)->not->toContain($ids['red'])
        ->and($matched)->toContain($ids['green'])
        ->and($matched)->toContain($ids['blue'])
        ->and($matched)->toContain($ids['empty']);
});

it('matches any listed value with the in predicate on a json array column', function () {
    $ids = jsonTagProducts();

    $matched = $this->filter->apply(Product::query(), ['tag_list_in' => 'green,blue'], $this->allowed)
        ->whereKey([$ids['red'], $ids['green'], $ids['blue'], $ids['empty']])
        ->pluck('id')->sort()->values()->all();

    expect($matched)->toBe(collect([$ids['green'], $ids['blue']])->sort()->values()->all());
});

it('excludes every listed value with the not_in predicate on a json array column', function () {
    $ids = jsonTagProducts();

    $matched = $this->filter->apply(Product::query(), ['tag_list_not_in' => 'red,green'], $this->allowed)
        ->whereKey([$ids['red'], $ids['green'], $ids['blue'], $ids['empty']])
        ->pluck('id')->all();

    expect($matched)->toContain($ids['blue'])
        ->and($matched)->toContain($ids['empty'])
        ->and($matched)->not->toContain($ids['red'])
        ->and($matched)->not->toContain($ids['green']);
});

it('treats null-or-empty json arrays as blank', function () {
    $ids = jsonTagProducts();

    $matched = $this->filter->apply(Product::query(), ['tag_list_blank' => '1'], $this->allowed)
        ->whereKey([$ids['red'], $ids['green'], $ids['blue'], $ids['empty']])
        ->pluck('id')->all();

    // Only the empty-array product is blank.
    expect($matched)->toBe([$ids['empty']]);
});

it('treats non-empty json arrays as present/not_null', function () {
    $ids = jsonTagProducts();

    $matched = $this->filter->apply(Product::query(), ['tag_list_present' => '1'], $this->allowed)
        ->whereKey([$ids['red'], $ids['green'], $ids['blue'], $ids['empty']])
        ->pluck('id')->sort()->values()->all();

    expect($matched)->toBe(collect([$ids['red'], $ids['green'], $ids['blue']])->sort()->values()->all());
});

it('no-ops an unsupported scalar predicate on a json array column', function () {
    $ids = jsonTagProducts();

    // `lt` is meaningless on a JSON array, so applyJsonPredicate's default arm is a
    // no-op: the query is unconstrained and returns all four seeded products.
    $matched = $this->filter->apply(Product::query(), ['tag_list_lt' => 'red'], $this->allowed)
        ->whereKey([$ids['red'], $ids['green'], $ids['blue'], $ids['empty']])
        ->pluck('id')->all();

    expect($matched)->toHaveCount(4);
});
