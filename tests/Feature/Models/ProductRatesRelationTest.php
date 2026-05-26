<?php

use App\Models\Product;
use App\Models\ProductRate;

it('exposes a rates relation returning the product rate assignments', function () {
    $product = Product::factory()->create();
    ProductRate::factory()->count(2)->for($product)->create();
    ProductRate::factory()->create(); // belongs to a different product

    expect($product->rates)->toHaveCount(2)
        ->and($product->rates->first())->toBeInstanceOf(ProductRate::class);
});
