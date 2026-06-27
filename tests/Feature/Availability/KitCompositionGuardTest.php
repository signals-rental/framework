<?php

use App\Enums\KitComponentBinding;
use App\Models\Product;
use App\Models\SerialisedComponent;
use App\Services\Availability\KitCompositionGuard;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->guard = app(KitCompositionGuard::class);
    config(['availability.kit_nesting_max_depth' => 3]);
});

it('rejects a product being its own component', function () {
    $product = Product::factory()->kit()->create();

    expect(fn () => $this->guard->assertCanAdd($product->id, $product->id))
        ->toThrow(ValidationException::class, 'cannot be a component of itself');
});

it('rejects a circular kit composition', function () {
    $parent = Product::factory()->kit()->create();
    $child = Product::factory()->kit()->create();

    SerialisedComponent::factory()->pool()->quantity(1)->create([
        'product_id' => $parent->id,
        'component_product_id' => $child->id,
    ]);

    expect(fn () => $this->guard->assertCanAdd($child->id, $parent->id))
        ->toThrow(ValidationException::class, 'circular kit composition');
});

it('rejects compositions that exceed the configured nesting depth', function () {
    config(['availability.kit_nesting_max_depth' => 1]);

    $root = Product::factory()->kit()->create();
    $middle = Product::factory()->kit()->create();
    $leaf = Product::factory()->bulk()->create();

    SerialisedComponent::factory()->pool()->quantity(1)->create([
        'product_id' => $root->id,
        'component_product_id' => $middle->id,
    ]);

    expect(fn () => $this->guard->assertCanAdd($middle->id, $leaf->id))
        ->toThrow(ValidationException::class, 'maximum kit nesting depth');
});

it('allows a valid acyclic composition within the depth bound', function () {
    $kit = Product::factory()->kit()->create();
    $component = Product::factory()->bulk()->create();

    $this->guard->assertCanAdd($kit->id, $component->id);
})->throwsNoExceptions();

it('validates binding strings against the enum', function () {
    expect(KitCompositionGuard::isValidBinding(KitComponentBinding::Pool->value))->toBeTrue()
        ->and(KitCompositionGuard::isValidBinding(KitComponentBinding::Fixed->value))->toBeTrue()
        ->and(KitCompositionGuard::isValidBinding('not-a-binding'))->toBeFalse();
});

it('counts the component subtree depth when admitting a kit that already has children', function () {
    config(['availability.kit_nesting_max_depth' => 3]);

    // The component being added is itself a kit with one child, so its subtree
    // depth is 1 — the guard must walk into subtreeDepth()'s loop. Parent has no
    // ancestors, so resulting depth = 0 + 1 (parent→component) + 1 (component's
    // own subtree) = 2, within the bound of 3, and the add is allowed.
    $parent = Product::factory()->kit()->create();
    $component = Product::factory()->kit()->create();
    $grandchild = Product::factory()->bulk()->create();

    SerialisedComponent::factory()->pool()->quantity(1)->create([
        'product_id' => $component->id,
        'component_product_id' => $grandchild->id,
    ]);

    $this->guard->assertCanAdd($parent->id, $component->id);
})->throwsNoExceptions();

it('detects transitive cycles through nested components', function () {
    $a = Product::factory()->kit()->create();
    $b = Product::factory()->kit()->create();
    $c = Product::factory()->kit()->create();

    SerialisedComponent::factory()->pool()->quantity(1)->create([
        'product_id' => $a->id,
        'component_product_id' => $b->id,
    ]);
    SerialisedComponent::factory()->pool()->quantity(1)->create([
        'product_id' => $b->id,
        'component_product_id' => $c->id,
    ]);

    expect(fn () => $this->guard->assertCanAdd($c->id, $a->id))
        ->toThrow(ValidationException::class, 'circular kit composition');
});
