<?php

use App\Models\Product;
use App\Models\ProductRate;
use App\Models\RateDefinition;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->product = Product::factory()->create();
    $this->definition = RateDefinition::factory()->create();
});

describe('GET /api/v1/products/{product}/rates', function () {
    it('lists the rates for a product with pagination meta', function () {
        ProductRate::factory()->count(2)->create(['product_id' => $this->product->id, 'rate_definition_id' => $this->definition->id]);
        ProductRate::factory()->create(); // belongs to another product
        $token = $this->owner->createToken('test', ['rates:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/products/{$this->product->id}/rates")
            ->assertOk()
            ->assertJsonStructure([
                'product_rates' => [
                    '*' => ['id', 'product_id', 'rate_definition_id', 'transaction_type', 'transaction_type_name', 'price', 'currency', 'priority', 'created_at', 'updated_at'],
                ],
                'meta' => ['total', 'per_page', 'page'],
            ]);

        expect($response->json('product_rates'))->toHaveCount(2);
    });

    it('requires authentication', function () {
        $this->getJson("/api/v1/products/{$this->product->id}/rates")->assertUnauthorized();
    });

    it('requires rates:read ability', function () {
        $token = $this->owner->createToken('test', ['settings:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/products/{$this->product->id}/rates")
            ->assertForbidden();
    });
});

describe('GET /api/v1/products/{product}/rates/{rate}', function () {
    it('shows a single product rate', function () {
        $rate = ProductRate::factory()->create(['product_id' => $this->product->id, 'rate_definition_id' => $this->definition->id, 'price' => 5000]);
        $token = $this->owner->createToken('test', ['rates:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/products/{$this->product->id}/rates/{$rate->id}")
            ->assertOk()
            ->assertJsonPath('product_rate.id', $rate->id)
            ->assertJsonPath('product_rate.price', '50.00');
    });

    it('returns 404 when the rate belongs to a different product', function () {
        $otherProduct = Product::factory()->create();
        $rate = ProductRate::factory()->create(['product_id' => $otherProduct->id, 'rate_definition_id' => $this->definition->id]);
        $token = $this->owner->createToken('test', ['rates:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/products/{$this->product->id}/rates/{$rate->id}")
            ->assertNotFound();
    });
});

describe('POST /api/v1/products/{product}/rates', function () {
    it('creates a product rate', function () {
        $token = $this->owner->createToken('test', ['rates:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/products/{$this->product->id}/rates", [
                'rate_definition_id' => $this->definition->id,
                'transaction_type' => 'rental',
                'price' => 7500,
                'currency' => 'GBP',
                'priority' => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('product_rate.product_id', $this->product->id)
            ->assertJsonPath('product_rate.price', '75.00')
            ->assertJsonPath('meta.overlapping_rate_ids', []);

        $this->assertDatabaseHas('product_rates', ['product_id' => $this->product->id, 'price' => 7500]);
    });

    it('warns (non-blocking) when the new rate overlaps an existing one at the same priority', function () {
        $existing = ProductRate::factory()->create([
            'product_id' => $this->product->id,
            'rate_definition_id' => $this->definition->id,
            'store_id' => null,
            'transaction_type' => 'rental',
            'priority' => 0,
            'valid_from' => null,
            'valid_to' => null,
        ]);
        $token = $this->owner->createToken('test', ['rates:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/products/{$this->product->id}/rates", [
                'rate_definition_id' => $this->definition->id,
                'transaction_type' => 'rental',
                'price' => 5000,
                'currency' => 'GBP',
                'priority' => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('meta.overlapping_rate_ids', [$existing->id]);
    });

    it('validates required fields', function () {
        $token = $this->owner->createToken('test', ['rates:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/products/{$this->product->id}/rates", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rate_definition_id', 'transaction_type', 'price', 'currency']);
    });

    it('requires rates:write ability', function () {
        $token = $this->owner->createToken('test', ['rates:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/products/{$this->product->id}/rates", [
                'rate_definition_id' => $this->definition->id,
                'transaction_type' => 'rental',
                'price' => 1000,
                'currency' => 'GBP',
            ])
            ->assertForbidden();
    });
});

describe('PUT /api/v1/products/{product}/rates/{rate}', function () {
    it('updates a product rate', function () {
        $rate = ProductRate::factory()->create(['product_id' => $this->product->id, 'rate_definition_id' => $this->definition->id, 'price' => 1000]);
        $token = $this->owner->createToken('test', ['rates:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/products/{$this->product->id}/rates/{$rate->id}", ['price' => 9999])
            ->assertOk()
            ->assertJsonPath('product_rate.price', '99.99')
            ->assertJsonPath('meta.overlapping_rate_ids', []);
    });

    it('warns (non-blocking) when an update moves a rate into an overlap', function () {
        $existing = ProductRate::factory()->create([
            'product_id' => $this->product->id,
            'rate_definition_id' => $this->definition->id,
            'store_id' => null,
            'transaction_type' => 'rental',
            'priority' => 0,
            'valid_from' => null,
            'valid_to' => null,
        ]);
        $rate = ProductRate::factory()->create([
            'product_id' => $this->product->id,
            'rate_definition_id' => $this->definition->id,
            'store_id' => null,
            'transaction_type' => 'rental',
            'priority' => 5, // no overlap yet
            'valid_from' => null,
            'valid_to' => null,
        ]);
        $token = $this->owner->createToken('test', ['rates:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/products/{$this->product->id}/rates/{$rate->id}", ['priority' => 0])
            ->assertOk()
            ->assertJsonPath('meta.overlapping_rate_ids', [$existing->id]);
    });

    it('returns 404 when updating a rate that belongs to a different product', function () {
        $otherProduct = Product::factory()->create();
        $rate = ProductRate::factory()->create(['product_id' => $otherProduct->id, 'rate_definition_id' => $this->definition->id]);
        $token = $this->owner->createToken('test', ['rates:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/products/{$this->product->id}/rates/{$rate->id}", ['price' => 100])
            ->assertNotFound();
    });
});

describe('DELETE /api/v1/products/{product}/rates/{rate}', function () {
    it('deletes a product rate', function () {
        $rate = ProductRate::factory()->create(['product_id' => $this->product->id, 'rate_definition_id' => $this->definition->id]);
        $token = $this->owner->createToken('test', ['rates:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/products/{$this->product->id}/rates/{$rate->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('product_rates', ['id' => $rate->id]);
    });

    it('returns 404 when deleting a rate that belongs to a different product', function () {
        $otherProduct = Product::factory()->create();
        $rate = ProductRate::factory()->create(['product_id' => $otherProduct->id, 'rate_definition_id' => $this->definition->id]);
        $token = $this->owner->createToken('test', ['rates:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/products/{$this->product->id}/rates/{$rate->id}")
            ->assertNotFound();
    });
});
