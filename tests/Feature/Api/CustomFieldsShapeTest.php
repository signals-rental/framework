<?php

use App\Models\Member;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

/**
 * Regression coverage for todo #79 — empty custom_fields must serialise as a
 * JSON object `{}` (CRMS compatibility), not an array `[]`.
 *
 * NOTE: assertions check the raw response body. TestResponse::json() decodes
 * `{}` to a PHP `[]`, so it cannot distinguish the two — hence getContent().
 */
beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->token = $this->owner->createToken('test', ['products:read', 'stock:read', 'members:read'])->plainTextToken;
});

it('serialises empty custom_fields as a JSON object on a product', function () {
    $product = Product::factory()->create();

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson("/api/v1/products/{$product->id}")
        ->assertOk();

    expect($response->getContent())->toContain('"custom_fields":{}');
});

it('serialises empty custom_fields as a JSON object on a member', function () {
    $member = Member::factory()->create();

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson("/api/v1/members/{$member->id}")
        ->assertOk();

    expect($response->getContent())->toContain('"custom_fields":{}');
});

it('serialises empty custom_fields as a JSON object on a stock level', function () {
    $product = Product::factory()->create();
    $stock = StockLevel::factory()->create(['product_id' => $product->id]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson("/api/v1/stock_levels/{$stock->id}")
        ->assertOk();

    expect($response->getContent())->toContain('"custom_fields":{}');
});
