<?php

use App\Models\Accessory;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->product = Product::factory()->create();
});

describe('GET /api/v1/products/{product}/accessories', function () {
    it('lists accessories for a product', function () {
        $accessoryProduct = Product::factory()->create();
        Accessory::factory()->create([
            'product_id' => $this->product->id,
            'accessory_product_id' => $accessoryProduct->id,
        ]);
        $token = $this->owner->createToken('test', ['products:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/products/{$this->product->id}/accessories")
            ->assertOk()
            ->assertJsonStructure([
                'accessories' => [
                    '*' => ['id', 'product_id', 'accessory_product_id', 'quantity'],
                ],
                'meta',
            ]);
    });

    it('requires authentication', function () {
        $this->getJson("/api/v1/products/{$this->product->id}/accessories")
            ->assertUnauthorized();
    });

    it('returns forbidden without proper ability', function () {
        $token = $this->owner->createToken('test', ['activities:read'])->plainTextToken;
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/products/{$this->product->id}/accessories")
            ->assertForbidden();
    });
});

describe('POST /api/v1/products/{product}/accessories', function () {
    it('creates an accessory', function () {
        $accessoryProduct = Product::factory()->create();
        $token = $this->owner->createToken('test', ['products:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/products/{$this->product->id}/accessories", [
                'accessory_product_id' => $accessoryProduct->id,
                'quantity' => 2,
            ])
            ->assertCreated()
            ->assertJsonPath('accessory.product_id', $this->product->id)
            ->assertJsonPath('accessory.accessory_product_id', $accessoryProduct->id);
    });

    it('requires authentication', function () {
        $this->postJson("/api/v1/products/{$this->product->id}/accessories", [
            'accessory_product_id' => Product::factory()->create()->id,
            'quantity' => 1,
        ])->assertUnauthorized();
    });
});

describe('DELETE /api/v1/products/{product}/accessories/{accessory}', function () {
    it('deletes an accessory', function () {
        $accessory = Accessory::factory()->create(['product_id' => $this->product->id]);
        $token = $this->owner->createToken('test', ['products:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/products/{$this->product->id}/accessories/{$accessory->id}")
            ->assertNoContent();

        expect(Accessory::find($accessory->id))->toBeNull();
    });
});
