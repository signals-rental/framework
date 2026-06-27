<?php

use App\Enums\ContainerAvailabilityMode;
use App\Models\Container;
use App\Models\Product;
use App\Models\SerialisedComponent;
use App\Models\StockLevel;

describe('Product::componentOf relation', function () {
    it('returns kit rows where this product is used as a component', function () {
        $component = Product::factory()->create();
        $kitA = Product::factory()->create();
        $kitB = Product::factory()->create();

        SerialisedComponent::factory()->create([
            'product_id' => $kitA->id,
            'component_product_id' => $component->id,
        ]);
        SerialisedComponent::factory()->create([
            'product_id' => $kitB->id,
            'component_product_id' => $component->id,
        ]);

        expect($component->componentOf)->toHaveCount(2);
        expect($component->componentOf->pluck('product_id')->all())
            ->toEqualCanonicalizing([$kitA->id, $kitB->id]);
    });
});

describe('Product::containers relation', function () {
    it('returns containers backed by this product', function () {
        $product = Product::factory()->containerable()->create();
        $serialised = StockLevel::factory()->serialised()->create(['product_id' => $product->id]);
        $container = Container::factory()->create([
            'product_id' => $product->id,
            'serialised_item_id' => $serialised->id,
        ]);

        expect($product->containers)->toHaveCount(1);
        expect($product->containers->first()->id)->toBe($container->id);
    });
});

describe('Product::isContainerable', function () {
    it('returns true for a containerable product and false otherwise', function () {
        $containerable = Product::factory()->containerable()->create();
        $plain = Product::factory()->create();

        expect($containerable->isContainerable())->toBeTrue();
        expect($plain->isContainerable())->toBeFalse();
    });
});

describe('Product::containerMode', function () {
    it('returns the configured mode', function () {
        $product = Product::factory()->containerable(ContainerAvailabilityMode::Kit)->create();

        expect($product->containerMode())->toBe(ContainerAvailabilityMode::Kit);
    });

    it('defaults to transport when unset', function () {
        $product = Product::factory()->create(['container_availability_mode' => null]);

        expect($product->containerMode())->toBe(ContainerAvailabilityMode::Transport);
    });
});
