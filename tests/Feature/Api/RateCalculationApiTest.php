<?php

use App\Models\Product;
use App\Models\ProductRate;
use App\Models\RateDefinition;
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
});

describe('POST /api/v1/products/{product}/calculate_rate', function () {
    it('calculates a breakdown from the resolved product rate', function () {
        $definition = RateDefinition::factory()->create(); // period / daily
        ProductRate::factory()->create([
            'product_id' => $this->product->id,
            'rate_definition_id' => $definition->id,
            'store_id' => null,
            'transaction_type' => 'rental',
            'price' => 10000,
            'currency' => 'GBP',
            'valid_from' => null,
            'valid_to' => null,
            'priority' => 0,
        ]);
        $token = $this->owner->createToken('test', ['rates:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/products/{$this->product->id}/calculate_rate", [
                'quantity' => 1,
                'start' => '2026-01-05T00:00:00Z',
                'end' => '2026-01-10T00:00:00Z',
                'transaction_type' => 'rental',
            ])
            ->assertOk()
            ->assertJsonPath('meta.resolved', true)
            ->assertJsonPath('meta.rate_definition_id', $definition->id)
            ->assertJsonPath('rate_breakdown.currency', 'GBP')
            ->assertJsonPath('rate_breakdown.units', 5)
            ->assertJsonPath('rate_breakdown.unit_label', 'days')
            ->assertJsonPath('rate_breakdown.unit_price', '100.00')
            ->assertJsonPath('rate_breakdown.total', '500.00');
    });

    it('applies quantity to the total', function () {
        $definition = RateDefinition::factory()->create();
        ProductRate::factory()->create([
            'product_id' => $this->product->id,
            'rate_definition_id' => $definition->id,
            'transaction_type' => 'rental',
            'price' => 10000,
            'currency' => 'GBP',
        ]);
        $token = $this->owner->createToken('test', ['rates:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/products/{$this->product->id}/calculate_rate", [
                'quantity' => 8,
                'start' => '2026-01-05T00:00:00Z',
                'end' => '2026-01-10T00:00:00Z',
            ])
            ->assertOk()
            ->assertJsonPath('rate_breakdown.quantity', 8)
            ->assertJsonPath('rate_breakdown.total', '4000.00');
    });

    it('preserves money precision through the factor modifier (HALF_UP, not truncation)', function () {
        // £24.69/day over 5 days = 12345 minor units; factor 0.9 gives 12345 * 0.9 = 11110.5,
        // which must round HALF_UP to 11111 (£111.11). Naive integer truncation would yield
        // 11110 (£111.10). This guards the lossless brick/money path end to end via the API.
        $definition = RateDefinition::factory()->create([
            'enabled_modifiers' => ['factor'],
            'modifier_configs' => [
                'factor' => ['ranges' => [
                    ['from' => 1, 'to' => null, 'factor' => '0.9'],
                ]],
            ],
        ]);
        ProductRate::factory()->create([
            'product_id' => $this->product->id,
            'rate_definition_id' => $definition->id,
            'transaction_type' => 'rental',
            'price' => 2469,
            'currency' => 'GBP',
        ]);
        $token = $this->owner->createToken('test', ['rates:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/products/{$this->product->id}/calculate_rate", [
                'quantity' => 1,
                'start' => '2026-01-05T00:00:00Z',
                'end' => '2026-01-10T00:00:00Z',
                'transaction_type' => 'rental',
            ])
            ->assertOk()
            ->assertJsonPath('rate_breakdown.unit_price', '24.69')
            ->assertJsonPath('rate_breakdown.units', 5)
            ->assertJsonPath('rate_breakdown.per_unit_subtotal', '111.11')
            ->assertJsonPath('rate_breakdown.total', '111.11');
    });

    it('resolves the store-specific rate when store_id is supplied', function () {
        $store = Store::factory()->create();
        $definition = RateDefinition::factory()->create();
        // An all-stores rate and a store-specific rate at the same priority; the
        // store-specific one must win when its store_id is passed.
        ProductRate::factory()->create([
            'product_id' => $this->product->id,
            'rate_definition_id' => $definition->id,
            'store_id' => null,
            'transaction_type' => 'rental',
            'price' => 5000,
            'currency' => 'GBP',
            'priority' => 0,
        ]);
        $storeRate = ProductRate::factory()->create([
            'product_id' => $this->product->id,
            'rate_definition_id' => $definition->id,
            'store_id' => $store->id,
            'transaction_type' => 'rental',
            'price' => 10000,
            'currency' => 'GBP',
            'priority' => 0,
        ]);
        $token = $this->owner->createToken('test', ['rates:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/products/{$this->product->id}/calculate_rate", [
                'quantity' => 1,
                'start' => '2026-01-05T00:00:00Z',
                'end' => '2026-01-10T00:00:00Z',
                'transaction_type' => 'rental',
                'store_id' => $store->id,
            ])
            ->assertOk()
            ->assertJsonPath('meta.resolved', true)
            ->assertJsonPath('meta.product_rate_id', $storeRate->id)
            ->assertJsonPath('rate_breakdown.unit_price', '100.00');
    });

    it('falls back to a zero-priced breakdown when no rate resolves', function () {
        $token = $this->owner->createToken('test', ['rates:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/products/{$this->product->id}/calculate_rate", [
                'quantity' => 1,
                'start' => '2026-01-05T00:00:00Z',
                'end' => '2026-01-10T00:00:00Z',
            ])
            ->assertOk()
            ->assertJsonPath('meta.resolved', false)
            ->assertJsonPath('meta.rate_definition_id', null)
            ->assertJsonPath('rate_breakdown.units', 5)
            ->assertJsonPath('rate_breakdown.unit_price', '0.00')
            ->assertJsonPath('rate_breakdown.total', '0.00');
    });

    it('validates required fields', function () {
        $token = $this->owner->createToken('test', ['rates:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/products/{$this->product->id}/calculate_rate", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity', 'start', 'end']);
    });

    it('requires authentication', function () {
        $this->postJson("/api/v1/products/{$this->product->id}/calculate_rate", [])
            ->assertUnauthorized();
    });

    it('forbids a token without rates:read or products:read', function () {
        $token = $this->owner->createToken('test', ['settings:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/products/{$this->product->id}/calculate_rate", [
                'quantity' => 1,
                'start' => '2026-01-05T00:00:00Z',
                'end' => '2026-01-10T00:00:00Z',
            ])
            ->assertForbidden();
    });

    it('allows a token scoped to products:read', function () {
        ProductRate::factory()->create([
            'product_id' => $this->product->id,
            'transaction_type' => 'rental',
            'price' => 10000,
            'currency' => 'GBP',
        ]);
        $token = $this->owner->createToken('test', ['products:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/products/{$this->product->id}/calculate_rate", [
                'quantity' => 1,
                'start' => '2026-01-05T00:00:00Z',
                'end' => '2026-01-10T00:00:00Z',
            ])
            ->assertOk();
    });
});
