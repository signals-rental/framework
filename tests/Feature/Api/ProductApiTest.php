<?php

use App\Models\Accessory;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

describe('GET /api/v1/products', function () {
    it('lists products with pagination meta', function () {
        Product::factory()->count(3)->create();
        $token = $this->owner->createToken('test', ['products:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonStructure([
                'products' => [
                    '*' => ['id', 'name', 'type', 'active', 'created_at', 'updated_at'],
                ],
                'meta' => ['total', 'per_page', 'page'],
            ])
            ->assertJsonPath('meta.total', 3);
    });

    it('filters by name', function () {
        Product::factory()->create(['name' => 'LED Par Can']);
        Product::factory()->create(['name' => 'Moving Head']);
        $token = $this->owner->createToken('test', ['products:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/products?q[name_eq]=LED Par Can')
            ->assertOk();

        expect($response->json('products'))->toHaveCount(1);
        expect($response->json('products.0.name'))->toBe('LED Par Can');
    });

    it('filters by product_type', function () {
        Product::factory()->rental()->create();
        Product::factory()->sale()->create();
        $token = $this->owner->createToken('test', ['products:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/products?q[product_type_eq]=sale')
            ->assertOk();

        expect($response->json('products'))->toHaveCount(1);
        expect($response->json('products.0.type'))->toBe('Sale');
    });

    it('sorts by name', function () {
        Product::factory()->create(['name' => 'Zebra Light']);
        Product::factory()->create(['name' => 'Alpha Speaker']);
        $token = $this->owner->createToken('test', ['products:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/products?sort=name')
            ->assertOk();

        expect($response->json('products.0.name'))->toBe('Alpha Speaker');
        expect($response->json('products.1.name'))->toBe('Zebra Light');
    });

    it('includes productGroup when requested', function () {
        $group = ProductGroup::factory()->create(['name' => 'Lighting']);
        Product::factory()->create(['product_group_id' => $group->id]);
        $token = $this->owner->createToken('test', ['products:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/products?include=productGroup')
            ->assertOk();

        expect($response->json('products.0.product_group'))->not->toBeNull();
        expect($response->json('products.0.product_group.name'))->toBe('Lighting');
    });

    it('requires authentication', function () {
        $this->getJson('/api/v1/products')
            ->assertUnauthorized();
    });

    it('requires products:read ability', function () {
        $token = $this->owner->createToken('test', ['settings:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/products')
            ->assertForbidden();
    });
});

describe('GET /api/v1/products/{id}', function () {
    it('shows a single product', function () {
        $product = Product::factory()->create(['name' => 'Test Speaker']);
        $token = $this->owner->createToken('test', ['products:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('product.name', 'Test Speaker');
    });

    it('includes accessories when requested', function () {
        $product = Product::factory()->create();
        $accessoryProduct = Product::factory()->create(['name' => 'Cable']);
        Accessory::factory()->create([
            'product_id' => $product->id,
            'accessory_product_id' => $accessoryProduct->id,
        ]);
        $token = $this->owner->createToken('test', ['products:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/products/{$product->id}?include=accessories,accessories.accessoryProduct")
            ->assertOk();

        expect($response->json('product.accessories'))->toHaveCount(1);
        expect($response->json('product.accessories.0.related_name'))->toBe('Cable');
    });

    it('returns 404 for non-existent product', function () {
        $token = $this->owner->createToken('test', ['products:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/products/99999')
            ->assertNotFound();
    });
});

describe('POST /api/v1/products', function () {
    it('creates a product', function () {
        $token = $this->owner->createToken('test', ['products:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/products', [
                'name' => 'New Speaker',
                'product_type' => 'rental',
            ])
            ->assertCreated()
            ->assertJsonPath('product.name', 'New Speaker')
            ->assertJsonPath('product.type', 'Rental');

        $this->assertDatabaseHas('products', ['name' => 'New Speaker']);
    });

    it('validates required fields', function () {
        $token = $this->owner->createToken('test', ['products:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/products', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('requires products:write ability', function () {
        $token = $this->owner->createToken('test', ['products:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/products', ['name' => 'Test'])
            ->assertForbidden();
    });
});

describe('PUT /api/v1/products/{id}', function () {
    it('updates a product', function () {
        $product = Product::factory()->create(['name' => 'Old Name']);
        $token = $this->owner->createToken('test', ['products:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/products/{$product->id}", [
                'name' => 'New Name',
            ])
            ->assertOk()
            ->assertJsonPath('product.name', 'New Name');
    });
});

describe('DELETE /api/v1/products/{id}', function () {
    it('soft-deletes a product', function () {
        $product = Product::factory()->create();
        $token = $this->owner->createToken('test', ['products:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/products/{$product->id}")
            ->assertNoContent();

        $this->assertDatabaseHas('products', ['id' => $product->id]);
        expect(Product::withTrashed()->find($product->id)->trashed())->toBeTrue();
    });
});

describe('CRMS response shape', function () {
    it('returns the complete CRMS-compatible field set', function () {
        $group = ProductGroup::factory()->create(['name' => 'Lighting']);
        $product = Product::factory()->create([
            'name' => 'LED Par Can',
            'description' => 'A professional LED wash fixture',
            'product_type' => \App\Enums\ProductType::Rental,
            'product_group_id' => $group->id,
            'is_active' => true,
            'allowed_stock_type' => 3,
            'stock_method' => \App\Enums\StockMethod::Serialised,
            'replacement_charge' => 10000,
            'sub_rental_price' => 5000,
            'purchase_price' => 25000,
            'weight' => '5.5000',
            'barcode' => '5060001234567',
            'sku' => 'LED-PAR-001',
            'accessory_only' => false,
            'system' => false,
            'discountable' => true,
            'buffer_percent' => '10.0',
            'post_rent_unavailability' => 60,
            'tag_list' => ['lighting', 'led'],
        ]);
        $token = $this->owner->createToken('test', ['products:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/products/{$product->id}?include=productGroup")
            ->assertOk();

        $data = $response->json('product');

        // Core fields
        expect($data['id'])->toBe($product->id);
        expect($data['name'])->toBe('LED Par Can');
        expect($data['type'])->toBe('Rental');
        expect($data['description'])->toBe('A professional LED wash fixture');
        expect($data['product_group_id'])->toBe($group->id);

        // CRMS mapped: is_active → active
        expect($data)->toHaveKey('active');
        expect($data)->not->toHaveKey('is_active');
        expect($data['active'])->toBeTrue();

        // Stock fields
        expect($data['allowed_stock_type'])->toBe(3);
        expect($data['allowed_stock_type_name'])->toBe('Both');
        expect($data['stock_method'])->toBe(2);
        expect($data['stock_method_name'])->toBe('Serialised');

        // Money fields as decimal strings (minor units → decimal)
        expect($data['replacement_charge'])->toBe('100.00');
        expect($data['sub_rental_price'])->toBe('50.00');
        expect($data['purchase_price'])->toBe('250.00');

        // Decimal fields as formatted strings
        expect($data['weight'])->toBe('5.5');
        expect($data['buffer_percent'])->toBe('10.0');

        // Integer fields
        expect($data['post_rent_unavailability'])->toBe(60);

        // Identification
        expect($data['barcode'])->toBe('5060001234567');
        expect($data['sku'])->toBe('LED-PAR-001');

        // Boolean flags
        expect($data['accessory_only'])->toBeFalse();
        expect($data['system'])->toBeFalse();
        expect($data['discountable'])->toBeTrue();

        // Tag list as array
        expect($data['tag_list'])->toBe(['lighting', 'led']);

        // Custom fields as flat object
        expect($data['custom_fields'])->toBeArray();

        // Nested relationship objects
        expect($data['product_group'])->toBe(['id' => $group->id, 'name' => 'Lighting']);

        // CRMS date format: 2026-03-21T14:30:45.123Z
        expect($data['created_at'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/');
        expect($data['updated_at'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/');
    });

    it('returns correct list response with wrapping and meta', function () {
        Product::factory()->count(3)->create();
        $token = $this->owner->createToken('test', ['products:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonStructure([
                'products' => [
                    '*' => [
                        'id', 'name', 'type', 'description', 'product_group_id',
                        'active', 'allowed_stock_type', 'allowed_stock_type_name',
                        'stock_method', 'stock_method_name', 'buffer_percent',
                        'post_rent_unavailability', 'replacement_charge', 'weight',
                        'barcode', 'sku', 'accessory_only', 'system', 'discountable',
                        'tag_list', 'custom_fields', 'created_at', 'updated_at',
                    ],
                ],
                'meta' => ['total', 'per_page', 'page'],
            ]);

        // Verify wrapping key
        expect($response->json())->toHaveKeys(['products', 'meta']);
        expect($response->json('meta.total'))->toBe(3);
        expect($response->json('meta.per_page'))->toBe(20);
        expect($response->json('meta.page'))->toBe(1);
    });

    it('returns accessories with CRMS field names', function () {
        $product = Product::factory()->create();
        $accessoryProduct = Product::factory()->create(['name' => 'XLR Cable']);
        Accessory::factory()->create([
            'product_id' => $product->id,
            'accessory_product_id' => $accessoryProduct->id,
            'quantity' => 3.0,
        ]);
        $token = $this->owner->createToken('test', ['products:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/products/{$product->id}?include=accessories,accessories.accessoryProduct")
            ->assertOk();

        $acc = $response->json('product.accessories.0');
        expect($acc)->toHaveKeys(['id', 'related_id', 'related_name', 'quantity']);
        expect($acc['related_id'])->toBe($accessoryProduct->id);
        expect($acc['related_name'])->toBe('XLR Cable');
        expect($acc['quantity'])->toBe('3.0');
    });

    it('includes custom_fields by default', function () {
        $product = Product::factory()->create();
        $token = $this->owner->createToken('test', ['products:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/products/{$product->id}")
            ->assertOk();

        expect($response->json('product.custom_fields'))->toBeArray();
    });

    it('returns null for optional nullable fields', function () {
        $product = Product::factory()->create([
            'description' => null,
            'weight' => null,
            'barcode' => null,
            'sku' => null,
            'tax_class_id' => null,
            'country_of_origin_id' => null,
        ]);
        $token = $this->owner->createToken('test', ['products:read'])->plainTextToken;

        $data = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/products/{$product->id}")
            ->assertOk()
            ->json('product');

        expect($data['description'])->toBeNull();
        expect($data['weight'])->toBeNull();
        expect($data['barcode'])->toBeNull();
        expect($data['sku'])->toBeNull();
        expect($data['tax_class_id'])->toBeNull();
        expect($data['country_of_origin_id'])->toBeNull();
    });
});

describe('product accessories API', function () {
    it('lists accessories for a product', function () {
        $product = Product::factory()->create();
        $accessoryProduct = Product::factory()->create(['name' => 'Cable']);
        Accessory::factory()->create([
            'product_id' => $product->id,
            'accessory_product_id' => $accessoryProduct->id,
            'quantity' => 2,
        ]);
        $token = $this->owner->createToken('test', ['products:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/products/{$product->id}/accessories")
            ->assertOk();

        expect($response->json('accessories'))->toHaveCount(1);
        expect($response->json('accessories.0.related_name'))->toBe('Cable');
        expect($response->json('accessories.0.quantity'))->toBe('2.0');
    });

    it('creates an accessory for a product', function () {
        $product = Product::factory()->create();
        $accessoryProduct = Product::factory()->create(['name' => 'Power Lead']);
        $token = $this->owner->createToken('test', ['products:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/products/{$product->id}/accessories", [
                'accessory_product_id' => $accessoryProduct->id,
                'quantity' => 1,
                'included' => true,
                'zero_priced' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('accessory.related_name', 'Power Lead')
            ->assertJsonPath('accessory.included', true);
    });

    it('deletes an accessory from a product', function () {
        $product = Product::factory()->create();
        $accessory = Accessory::factory()->create(['product_id' => $product->id]);
        $token = $this->owner->createToken('test', ['products:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/products/{$product->id}/accessories/{$accessory->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('accessories', ['id' => $accessory->id]);
    });

    it('returns 404 when deleting an accessory that belongs to a different product', function () {
        $product = Product::factory()->create();
        $otherProduct = Product::factory()->create();
        $accessory = Accessory::factory()->create(['product_id' => $otherProduct->id]);
        $token = $this->owner->createToken('test', ['products:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/products/{$product->id}/accessories/{$accessory->id}")
            ->assertNotFound();
    });
});
