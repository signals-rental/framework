<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create(['is_owner' => true, 'is_active' => true]);
    Sanctum::actingAs($this->user, ['*']);
});

describe('GET /api/v1/schema', function () {
    it('lists all available schema models', function () {
        $this->getJson('/api/v1/schema')
            ->assertOk()
            ->assertJsonStructure(['schemas'])
            ->assertJsonFragment(['schemas' => array_values(json_decode(json_encode([
                'members', 'stores', 'addresses', 'countries', 'currencies',
                'exchange_rates', 'emails', 'phones', 'links', 'attachments',
                'users', 'action_logs', 'webhooks', 'custom_views', 'tax_rates', 'tax_rules',
                'products', 'product_groups', 'stock_levels', 'stock_transactions', 'activities',
                'rate_definitions', 'product_rates', 'opportunities',
            ])))]);
    });
});

describe('GET /api/v1/schema/{model}', function () {
    it('returns schema for members', function () {
        $this->getJson('/api/v1/schema/members')
            ->assertOk()
            ->assertJsonStructure([
                'model',
                'model_class',
                'fields' => [
                    'name' => ['name', 'type', 'source', 'filterable', 'sortable', 'label'],
                ],
            ])
            ->assertJsonFragment(['model' => 'members', 'model_class' => 'Member']);
    });

    it('returns schema for stores', function () {
        $this->getJson('/api/v1/schema/stores')
            ->assertOk()
            ->assertJsonFragment(['model' => 'stores', 'model_class' => 'Store']);
    });

    it('returns schema for addresses', function () {
        $this->getJson('/api/v1/schema/addresses')
            ->assertOk()
            ->assertJsonPath('fields.city.type', 'string')
            ->assertJsonPath('fields.is_primary.type', 'boolean');
    });

    it('returns schema for countries', function () {
        $this->getJson('/api/v1/schema/countries')
            ->assertOk()
            ->assertJsonPath('fields.code.type', 'string')
            ->assertJsonPath('fields.is_active.type', 'boolean');
    });

    it('returns schema for users', function () {
        $this->getJson('/api/v1/schema/users')
            ->assertOk()
            ->assertJsonPath('fields.email.type', 'string')
            ->assertJsonPath('fields.is_active.type', 'boolean');
    });

    it('returns schema for rate_definitions (D2)', function () {
        $this->getJson('/api/v1/schema/rate_definitions')
            ->assertOk()
            ->assertJsonStructure(['model', 'model_class', 'fields'])
            ->assertJsonFragment(['model' => 'rate_definitions', 'model_class' => 'RateDefinition']);
    });

    it('returns schema for product_rates (D2)', function () {
        $this->getJson('/api/v1/schema/product_rates')
            ->assertOk()
            ->assertJsonStructure(['model', 'model_class', 'fields'])
            ->assertJsonFragment(['model' => 'product_rates', 'model_class' => 'ProductRate']);
    });

    it('returns schema for stock_transactions', function () {
        $this->getJson('/api/v1/schema/stock_transactions')
            ->assertOk()
            ->assertJsonStructure(['model', 'model_class', 'fields'])
            ->assertJsonFragment(['model' => 'stock_transactions', 'model_class' => 'StockTransaction']);
    });

    it('resolves a singular model name to its plural schema (D3)', function () {
        $this->getJson('/api/v1/schema/product')
            ->assertOk()
            ->assertJsonFragment(['model' => 'products', 'model_class' => 'Product']);

        $this->getJson('/api/v1/schema/activity')
            ->assertOk()
            ->assertJsonFragment(['model' => 'activities', 'model_class' => 'Activity']);
    });

    it('returns 404 for unknown model', function () {
        $this->getJson('/api/v1/schema/nonexistent')
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'Unknown schema model: nonexistent']);
    });
});
