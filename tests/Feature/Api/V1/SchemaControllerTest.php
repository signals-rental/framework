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

    it('returns 404 for unknown model', function () {
        $this->getJson('/api/v1/schema/nonexistent')
            ->assertNotFound()
            ->assertJsonFragment(['message' => 'Unknown schema model: nonexistent']);
    });
});
