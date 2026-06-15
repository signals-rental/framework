<?php

use App\Models\Product;
use App\Models\StockLevel;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

use function Pest\Laravel\assertSoftDeleted;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

describe('POST /api/v1/products/{product}/merge', function () {
    it('merges the secondary product into the primary and archives the secondary', function () {
        $primary = Product::factory()->rental()->create();
        $secondary = Product::factory()->rental()->create();
        // A stock level on the secondary should transfer to the primary.
        $stockLevel = StockLevel::factory()->create(['product_id' => $secondary->id]);
        $token = $this->owner->createToken('test', ['products:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/products/{$primary->id}/merge", [
                'secondary_id' => $secondary->id,
            ])
            ->assertOk()
            ->assertJsonPath('product.id', $primary->id);

        // Secondary is soft-deleted; its stock level now belongs to the primary.
        assertSoftDeleted('products', ['id' => $secondary->id]);
        $this->assertDatabaseHas('stock_levels', [
            'id' => $stockLevel->id,
            'product_id' => $primary->id,
        ]);
    });

    it('rejects merging products of different types', function () {
        $primary = Product::factory()->rental()->create();
        $secondary = Product::factory()->sale()->create();
        $token = $this->owner->createToken('test', ['products:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/products/{$primary->id}/merge", [
                'secondary_id' => $secondary->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('secondary_id');

        $this->assertDatabaseHas('products', ['id' => $secondary->id, 'deleted_at' => null]);
    });

    it('rejects merging a product into itself', function () {
        $primary = Product::factory()->rental()->create();
        $token = $this->owner->createToken('test', ['products:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/products/{$primary->id}/merge", [
                'secondary_id' => $primary->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('secondary_id');
    });

    it('requires authentication', function () {
        $primary = Product::factory()->rental()->create();
        $secondary = Product::factory()->rental()->create();

        $this->postJson("/api/v1/products/{$primary->id}/merge", [
            'secondary_id' => $secondary->id,
        ])->assertUnauthorized();
    });

    it('requires the products:write ability', function () {
        $primary = Product::factory()->rental()->create();
        $secondary = Product::factory()->rental()->create();
        $token = $this->owner->createToken('test', ['products:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/products/{$primary->id}/merge", [
                'secondary_id' => $secondary->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('products', ['id' => $secondary->id, 'deleted_at' => null]);
    });
});
