<?php

use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockTransaction;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->product = Product::factory()->create();
    $this->store = Store::factory()->create();
    $this->stockLevel = StockLevel::factory()->create([
        'product_id' => $this->product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 0,
    ]);
});

describe('GET stock_transactions', function () {
    it('lists transactions for a stock level', function () {
        StockTransaction::factory()->count(3)->create([
            'stock_level_id' => $this->stockLevel->id,
            'store_id' => $this->store->id,
        ]);
        $token = $this->owner->createToken('test', ['stock:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/products/{$this->product->id}/stock_levels/{$this->stockLevel->id}/stock_transactions")
            ->assertOk()
            ->assertJsonStructure([
                'stock_transactions' => [
                    '*' => ['id', 'stock_level_id', 'store_id', 'transaction_type', 'transaction_type_name', 'quantity', 'quantity_move', 'manual'],
                ],
                'meta' => ['total', 'per_page', 'page'],
            ])
            ->assertJsonPath('meta.total', 3);
    });

    it('requires authentication', function () {
        $this->getJson("/api/v1/products/{$this->product->id}/stock_levels/{$this->stockLevel->id}/stock_transactions")
            ->assertUnauthorized();
    });

    it('returns forbidden without proper ability', function () {
        $token = $this->owner->createToken('test', ['products:read'])->plainTextToken;
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/products/{$this->product->id}/stock_levels/{$this->stockLevel->id}/stock_transactions")
            ->assertForbidden();
    });
});

describe('POST stock_transactions', function () {
    it('creates a buy transaction', function () {
        $token = $this->owner->createToken('test', ['stock:write'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/products/{$this->product->id}/stock_levels/{$this->stockLevel->id}/stock_transactions", [
                'transaction_type' => 4,
                'quantity' => '5.0',
                'transaction_at' => now()->toISOString(),
            ])
            ->assertCreated();

        expect($response->json('stock_transaction.transaction_type'))->toBe(4);
        expect($response->json('stock_transaction.transaction_type_name'))->toBe('Buy');
        expect($response->json('stock_transaction.quantity'))->toBe('5.0');
        expect($response->json('stock_transaction.quantity_move'))->toBe('5.0');
        expect($response->json('stock_transaction.manual'))->toBeTrue();
    });

    it('updates stock level quantity on buy', function () {
        $token = $this->owner->createToken('test', ['stock:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/products/{$this->product->id}/stock_levels/{$this->stockLevel->id}/stock_transactions", [
                'transaction_type' => 4,
                'quantity' => '10.0',
                'transaction_at' => now()->toISOString(),
            ])
            ->assertCreated();

        $this->stockLevel->refresh();
        expect((float) $this->stockLevel->quantity_held)->toBe(10.0);
    });

    it('rejects invalid transaction types', function () {
        $token = $this->owner->createToken('test', ['stock:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/products/{$this->product->id}/stock_levels/{$this->stockLevel->id}/stock_transactions", [
                'transaction_type' => 1,
                'quantity' => '1.0',
                'transaction_at' => now()->toISOString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('transaction_type');
    });

    it('requires authentication', function () {
        $this->postJson("/api/v1/products/{$this->product->id}/stock_levels/{$this->stockLevel->id}/stock_transactions", [
            'transaction_type' => 4,
            'quantity' => '1.0',
            'transaction_at' => now()->toISOString(),
        ])->assertUnauthorized();
    });

    it('returns 404 when stock level does not belong to product', function () {
        $otherProduct = \App\Models\Product::factory()->create();
        $token = $this->owner->createToken('test', ['stock:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/products/{$otherProduct->id}/stock_levels/{$this->stockLevel->id}/stock_transactions", [
                'transaction_type' => 4,
                'quantity' => '1.0',
                'transaction_at' => now()->toISOString(),
            ])
            ->assertNotFound();
    });

    it('matches CRMS response shape', function () {
        $token = $this->owner->createToken('test', ['stock:write'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/products/{$this->product->id}/stock_levels/{$this->stockLevel->id}/stock_transactions", [
                'transaction_type' => 5,
                'quantity' => '2.0',
                'transaction_at' => '2015-06-28T00:00:00.000Z',
                'description' => 'Found in warehouse',
            ])
            ->assertCreated();

        $data = $response->json('stock_transaction');
        expect($data)->toHaveKeys([
            'id', 'stock_level_id', 'store_id', 'source_id', 'source_type',
            'transaction_type', 'transaction_type_name', 'transaction_at',
            'quantity', 'quantity_move', 'description', 'manual',
            'created_at', 'updated_at',
        ]);
    });
});
