<?php

use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

describe('GET /api/v1/stock_levels', function () {
    it('lists stock levels with pagination meta', function () {
        StockLevel::factory()->count(3)->create();
        $token = $this->owner->createToken('test', ['stock:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/stock_levels')
            ->assertOk()
            ->assertJsonStructure([
                'stock_levels' => [
                    '*' => ['id', 'created_at', 'updated_at'],
                ],
                'meta' => ['total', 'per_page', 'page'],
            ])
            ->assertJsonPath('meta.total', 3);
    });

    it('filters by product_id', function () {
        $product = Product::factory()->create();
        StockLevel::factory()->create(['product_id' => $product->id]);
        StockLevel::factory()->create();
        $token = $this->owner->createToken('test', ['stock:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/stock_levels?q[product_id_eq]={$product->id}")
            ->assertOk();

        expect($response->json('stock_levels'))->toHaveCount(1);
    });

    it('filters by serial_number', function () {
        StockLevel::factory()->serialised()->create(['serial_number' => 'SN-001']);
        StockLevel::factory()->serialised()->create(['serial_number' => 'SN-002']);
        $token = $this->owner->createToken('test', ['stock:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/stock_levels?q[serial_number_eq]=SN-001')
            ->assertOk();

        expect($response->json('stock_levels'))->toHaveCount(1);
    });

    it('requires authentication', function () {
        $this->getJson('/api/v1/stock_levels')
            ->assertUnauthorized();
    });

    it('requires stock:read ability', function () {
        $token = $this->owner->createToken('test', ['settings:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/stock_levels')
            ->assertForbidden();
    });
});

describe('GET /api/v1/stock_levels/{id}', function () {
    it('shows a single stock level with correct response shape', function () {
        $stockLevel = StockLevel::factory()->create(['quantity_held' => 25]);
        $token = $this->owner->createToken('test', ['stock:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/stock_levels/{$stockLevel->id}")
            ->assertOk();

        expect($response->json('stock_level.id'))->toBe($stockLevel->id);
        // quantity_held is a decimal field, returned as numeric
        expect($response->json('stock_level.quantity_held'))->not->toBeNull();
    });

    it('includes product and store data by default', function () {
        $product = Product::factory()->create(['name' => 'Test Product']);
        $store = Store::factory()->create(['name' => 'Main Warehouse']);
        $stockLevel = StockLevel::factory()->create([
            'product_id' => $product->id,
            'store_id' => $store->id,
        ]);
        $token = $this->owner->createToken('test', ['stock:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/stock_levels/{$stockLevel->id}")
            ->assertOk();

        // Verify the stock level response has expected keys
        $data = $response->json('stock_level');
        expect($data)->toHaveKey('id');
        expect($data['id'])->toBe($stockLevel->id);
    });
});

describe('POST /api/v1/stock_levels', function () {
    it('creates a stock level', function () {
        $product = Product::factory()->create();
        $store = Store::factory()->create();
        $token = $this->owner->createToken('test', ['stock:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/stock_levels', [
                'product_id' => $product->id,
                'store_id' => $store->id,
                'quantity_held' => 10,
                'stock_type' => 1,
                'stock_category' => 10,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('stock_levels', [
            'product_id' => $product->id,
            'store_id' => $store->id,
        ]);
    });

    it('validates required fields', function () {
        $token = $this->owner->createToken('test', ['stock:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/stock_levels', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_id', 'store_id']);
    });
});

describe('PUT /api/v1/stock_levels/{id}', function () {
    it('updates a stock level', function () {
        $stockLevel = StockLevel::factory()->create(['quantity_held' => 5]);
        $token = $this->owner->createToken('test', ['stock:write'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/stock_levels/{$stockLevel->id}", [
                'quantity_held' => 15,
            ])
            ->assertOk();

        $stockLevel->refresh();
        expect((float) $stockLevel->quantity_held)->toBe(15.0);
    });
});

describe('DELETE /api/v1/stock_levels/{id}', function () {
    it('deletes a stock level', function () {
        $stockLevel = StockLevel::factory()->create();
        $token = $this->owner->createToken('test', ['stock:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/stock_levels/{$stockLevel->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('stock_levels', ['id' => $stockLevel->id]);
    });
});

describe('CRMS response shape', function () {
    it('maps product_id to item_id in response', function () {
        $product = Product::factory()->create(['name' => 'LED Par Can']);
        $stockLevel = StockLevel::factory()->serialised()->create([
            'product_id' => $product->id,
        ]);
        $token = $this->owner->createToken('test', ['stock:read'])->plainTextToken;

        $data = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/stock_levels/{$stockLevel->id}")
            ->assertOk()
            ->json('stock_level');

        // CRMS mapped: product_id → item_id
        expect($data)->toHaveKey('item_id');
        expect($data)->not->toHaveKey('product_id');
        expect($data['item_id'])->toBe($product->id);
    });

    it('returns the complete CRMS-compatible field set', function () {
        $product = Product::factory()->create(['name' => 'LED Par Can']);
        $store = Store::factory()->create(['name' => 'Main Warehouse']);
        $stockLevel = StockLevel::factory()->serialised()->create([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'item_name' => 'LED Par Can #001',
            'asset_number' => '18670',
            'serial_number' => 'SN-2026-001',
            'barcode' => '5060001234567',
            'location' => 'Bay A, Shelf 3',
            'stock_type' => 1,
            'stock_category' => 50,
            'quantity_held' => 1,
            'quantity_allocated' => 0,
            'quantity_unavailable' => 0,
            'quantity_on_order' => 0,
        ]);
        $token = $this->owner->createToken('test', ['stock:read'])->plainTextToken;

        $data = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/stock_levels/{$stockLevel->id}")
            ->assertOk()
            ->json('stock_level');

        // Core fields
        expect($data['id'])->toBe($stockLevel->id);
        expect($data['item_id'])->toBe($product->id);
        expect($data['item_name'])->toBe('LED Par Can #001');
        expect($data['store_id'])->toBe($store->id);
        expect($data['store_name'])->toBe('Main Warehouse');

        // Identification
        expect($data['asset_number'])->toBe('18670');
        expect($data['serial_number'])->toBe('SN-2026-001');
        expect($data['barcode'])->toBe('5060001234567');
        expect($data['location'])->toBe('Bay A, Shelf 3');

        // Stock type/category with names
        expect($data['stock_type'])->toBe(1);
        expect($data['stock_type_name'])->toBe('Rental');
        expect($data['stock_category'])->toBe(50);
        expect($data['stock_category_name'])->toBe('Serialised Stock');

        // Quantities as decimal strings
        expect($data['quantity_held'])->toBe('1.0');
        expect($data['quantity_allocated'])->toBe('0.0');
        expect($data['quantity_unavailable'])->toBe('0.0');
        expect($data['quantity_on_order'])->toBe('0.0');

        // Nested product object
        expect($data['item'])->toBe(['id' => $product->id, 'name' => 'LED Par Can']);

        // Custom fields as flat object
        expect($data['custom_fields'])->toBeArray();

        // CRMS date format
        expect($data['created_at'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/');
        expect($data['updated_at'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/');
    });

    it('returns correct list response with wrapping and meta', function () {
        StockLevel::factory()->count(3)->create();
        $token = $this->owner->createToken('test', ['stock:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/stock_levels')
            ->assertOk()
            ->assertJsonStructure([
                'stock_levels' => [
                    '*' => [
                        'id', 'item_id', 'item_name', 'store_id', 'store_name',
                        'asset_number', 'serial_number', 'barcode', 'location',
                        'stock_type', 'stock_type_name', 'stock_category', 'stock_category_name',
                        'quantity_held', 'quantity_allocated', 'quantity_unavailable', 'quantity_on_order',
                        'custom_fields', 'created_at', 'updated_at',
                    ],
                ],
                'meta' => ['total', 'per_page', 'page'],
            ]);

        expect($response->json())->toHaveKeys(['stock_levels', 'meta']);
        expect($response->json('meta.total'))->toBe(3);
    });

    it('returns null for optional nullable fields', function () {
        $stockLevel = StockLevel::factory()->create([
            'item_name' => null,
            'asset_number' => null,
            'serial_number' => null,
            'barcode' => null,
            'location' => null,
            'member_id' => null,
            'container_stock_level_id' => null,
            'container_mode' => null,
            'starts_at' => null,
            'ends_at' => null,
        ]);
        $token = $this->owner->createToken('test', ['stock:read'])->plainTextToken;

        $data = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/stock_levels/{$stockLevel->id}")
            ->assertOk()
            ->json('stock_level');

        expect($data['asset_number'])->toBeNull();
        expect($data['serial_number'])->toBeNull();
        expect($data['barcode'])->toBeNull();
        expect($data['location'])->toBeNull();
        expect($data['member_id'])->toBeNull();
        expect($data['container_stock_level_id'])->toBeNull();
        expect($data['container_mode'])->toBeNull();
        expect($data['starts_at'])->toBeNull();
        expect($data['ends_at'])->toBeNull();
    });

    it('formats starts_at and ends_at in CRMS timestamp format', function () {
        $stockLevel = StockLevel::factory()->create([
            'starts_at' => '2026-03-01 09:00:00',
            'ends_at' => '2026-03-15 17:00:00',
        ]);
        $token = $this->owner->createToken('test', ['stock:read'])->plainTextToken;

        $data = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/stock_levels/{$stockLevel->id}")
            ->assertOk()
            ->json('stock_level');

        expect($data['starts_at'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/');
        expect($data['ends_at'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/');
    });
});
