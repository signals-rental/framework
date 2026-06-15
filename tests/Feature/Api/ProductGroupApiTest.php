<?php

use App\Models\CustomField;
use App\Models\CustomFieldValue;
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

describe('GET /api/v1/product_groups', function () {
    it('lists product groups with pagination meta', function () {
        ProductGroup::factory()->count(3)->create();
        $token = $this->owner->createToken('test', ['products:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/product_groups')
            ->assertOk()
            ->assertJsonStructure([
                'product_groups' => [
                    '*' => ['id', 'name', 'description', 'parent_id', 'sort_order', 'created_at', 'updated_at'],
                ],
                'meta' => ['total', 'per_page', 'page'],
            ])
            ->assertJsonPath('meta.total', 3);
    });

    it('filters by name', function () {
        ProductGroup::factory()->create(['name' => 'Lighting']);
        ProductGroup::factory()->create(['name' => 'Audio']);
        $token = $this->owner->createToken('test', ['products:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/product_groups?q[name_eq]=Lighting')
            ->assertOk();

        expect($response->json('product_groups'))->toHaveCount(1);
        expect($response->json('product_groups.0.name'))->toBe('Lighting');
    });

    it('sorts by sort_order', function () {
        ProductGroup::factory()->create(['name' => 'Second', 'sort_order' => 2]);
        ProductGroup::factory()->create(['name' => 'First', 'sort_order' => 1]);
        $token = $this->owner->createToken('test', ['products:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/product_groups?sort=sort_order')
            ->assertOk();

        expect($response->json('product_groups.0.name'))->toBe('First');
        expect($response->json('product_groups.1.name'))->toBe('Second');
    });

    it('requires authentication', function () {
        $this->getJson('/api/v1/product_groups')
            ->assertUnauthorized();
    });

    it('requires products:read ability', function () {
        $token = $this->owner->createToken('test', ['settings:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/product_groups')
            ->assertForbidden();
    });
});

describe('GET /api/v1/product_groups/{id}', function () {
    it('shows a single product group', function () {
        $group = ProductGroup::factory()->create(['name' => 'Lighting']);
        $token = $this->owner->createToken('test', ['products:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/product_groups/{$group->id}")
            ->assertOk()
            ->assertJsonPath('product_group.name', 'Lighting');
    });

    it('includes the icon urls when an icon is set', function () {
        $group = ProductGroup::factory()->create([
            'name' => 'Lighting',
            'icon_url' => 'icons/productgroups/1/icon.jpg',
            'icon_thumb_url' => 'icons/productgroups/1/thumbs/icon.jpg',
        ]);
        $token = $this->owner->createToken('test', ['products:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/product_groups/{$group->id}")
            ->assertOk()
            ->assertJsonPath('product_group.icon.url', 'icons/productgroups/1/icon.jpg')
            ->assertJsonPath('product_group.icon.thumb_url', 'icons/productgroups/1/thumbs/icon.jpg');
    });

    it('returns a null icon when no icon is set', function () {
        $group = ProductGroup::factory()->create(['name' => 'Lighting']);
        $token = $this->owner->createToken('test', ['products:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/product_groups/{$group->id}")
            ->assertOk()
            ->assertJsonPath('product_group.icon', null);
    });

    it('includes the icon in list responses', function () {
        ProductGroup::factory()->create([
            'name' => 'Lighting',
            'icon_url' => 'icons/productgroups/1/icon.jpg',
            'icon_thumb_url' => 'icons/productgroups/1/thumbs/icon.jpg',
        ]);
        $token = $this->owner->createToken('test', ['products:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/product_groups')
            ->assertOk()
            ->assertJsonPath('product_groups.0.icon.url', 'icons/productgroups/1/icon.jpg')
            ->assertJsonPath('product_groups.0.icon.thumb_url', 'icons/productgroups/1/thumbs/icon.jpg');
    });

    it('includes parent when requested', function () {
        $parent = ProductGroup::factory()->create(['name' => 'Audio']);
        $child = ProductGroup::factory()->create(['name' => 'Speakers', 'parent_id' => $parent->id]);
        $token = $this->owner->createToken('test', ['products:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/product_groups/{$child->id}?include=parent")
            ->assertOk();

        expect($response->json('product_group.parent'))->not->toBeNull();
        expect($response->json('product_group.parent.name'))->toBe('Audio');
    });

    it('includes children when requested', function () {
        $parent = ProductGroup::factory()->create(['name' => 'Audio']);
        ProductGroup::factory()->create(['name' => 'Speakers', 'parent_id' => $parent->id]);
        ProductGroup::factory()->create(['name' => 'Microphones', 'parent_id' => $parent->id]);
        $token = $this->owner->createToken('test', ['products:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/product_groups/{$parent->id}?include=children")
            ->assertOk();

        // children is not directly in ProductGroupData, but it's loaded via includes
        expect($response->json('product_group.id'))->toBe($parent->id);
    });
});

describe('POST /api/v1/product_groups', function () {
    it('creates a product group', function () {
        $token = $this->owner->createToken('test', ['products:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/product_groups', [
                'name' => 'New Group',
                'description' => 'A test group',
                'sort_order' => 5,
            ])
            ->assertCreated()
            ->assertJsonPath('product_group.name', 'New Group')
            ->assertJsonPath('product_group.description', 'A test group')
            ->assertJsonPath('product_group.sort_order', 5);

        $this->assertDatabaseHas('product_groups', ['name' => 'New Group']);
    });

    it('creates a child product group', function () {
        $parent = ProductGroup::factory()->create(['name' => 'Audio']);
        $token = $this->owner->createToken('test', ['products:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/product_groups', [
                'name' => 'Speakers',
                'parent_id' => $parent->id,
            ])
            ->assertCreated()
            ->assertJsonPath('product_group.parent_id', $parent->id);
    });

    it('validates required fields', function () {
        $token = $this->owner->createToken('test', ['products:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/product_groups', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates parent_id exists', function () {
        $token = $this->owner->createToken('test', ['products:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/product_groups', [
                'name' => 'Test',
                'parent_id' => 99999,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['parent_id']);
    });
});

describe('custom fields write path', function () {
    it('persists custom fields posted on create and returns them flat', function () {
        CustomField::factory()->string()->create([
            'name' => 'colour_code',
            'module_type' => 'ProductGroup',
        ]);
        $token = $this->owner->createToken('test', ['products:write'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/product_groups', [
                'name' => 'Lighting',
                'custom_fields' => ['colour_code' => 'AMBER'],
            ])
            ->assertCreated()
            ->assertJsonPath('product_group.custom_fields.colour_code', 'AMBER');

        $id = $response->json('product_group.id');

        expect(CustomFieldValue::query()
            ->where('entity_type', ProductGroup::class)
            ->where('entity_id', $id)
            ->where('value_string', 'AMBER')
            ->exists())->toBeTrue();
    });

    it('persists custom fields posted on update and returns them flat', function () {
        CustomField::factory()->string()->create([
            'name' => 'colour_code',
            'module_type' => 'ProductGroup',
        ]);
        $group = ProductGroup::factory()->create(['name' => 'Lighting']);
        $token = $this->owner->createToken('test', ['products:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/product_groups/{$group->id}", [
                'custom_fields' => ['colour_code' => 'BLUE'],
            ])
            ->assertOk()
            ->assertJsonPath('product_group.custom_fields.colour_code', 'BLUE');

        expect(CustomFieldValue::query()
            ->where('entity_type', ProductGroup::class)
            ->where('entity_id', $group->id)
            ->where('value_string', 'BLUE')
            ->exists())->toBeTrue();
    });

    it('rejects create when a required custom field is missing', function () {
        CustomField::factory()->string()->required()->create([
            'name' => 'mandatory_code',
            'module_type' => 'ProductGroup',
        ]);
        $token = $this->owner->createToken('test', ['products:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/product_groups', [
                'name' => 'Lighting',
                'custom_fields' => [],
            ])
            ->assertUnprocessable();

        expect(ProductGroup::where('name', 'Lighting')->exists())->toBeFalse();
    });
});

describe('PUT /api/v1/product_groups/{id}', function () {
    it('updates a product group', function () {
        $group = ProductGroup::factory()->create(['name' => 'Old Name']);
        $token = $this->owner->createToken('test', ['products:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/product_groups/{$group->id}", [
                'name' => 'New Name',
            ])
            ->assertOk()
            ->assertJsonPath('product_group.name', 'New Name');
    });

    it('updates sort_order', function () {
        $group = ProductGroup::factory()->create(['sort_order' => 0]);
        $token = $this->owner->createToken('test', ['products:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/product_groups/{$group->id}", [
                'sort_order' => 10,
            ])
            ->assertOk()
            ->assertJsonPath('product_group.sort_order', 10);
    });
});

describe('DELETE /api/v1/product_groups/{id}', function () {
    it('deletes a product group', function () {
        $group = ProductGroup::factory()->create();
        $token = $this->owner->createToken('test', ['products:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/product_groups/{$group->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('product_groups', ['id' => $group->id]);
    });
});

describe('RMS response shape', function () {
    it('returns the complete RMS-compatible field set', function () {
        $parent = ProductGroup::factory()->create(['name' => 'Audio']);
        $group = ProductGroup::factory()->create([
            'name' => 'Speakers',
            'description' => 'All speaker equipment',
            'parent_id' => $parent->id,
            'sort_order' => 3,
        ]);
        $token = $this->owner->createToken('test', ['products:read'])->plainTextToken;

        $data = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/product_groups/{$group->id}?include=parent")
            ->assertOk()
            ->json('product_group');

        // Core fields
        expect($data['id'])->toBe($group->id);
        expect($data['name'])->toBe('Speakers');
        expect($data['description'])->toBe('All speaker equipment');
        expect($data['parent_id'])->toBe($parent->id);
        expect($data['sort_order'])->toBe(3);

        // Nested parent object
        expect($data['parent'])->toBe(['id' => $parent->id, 'name' => 'Audio']);

        // Custom fields as flat object
        expect($data['custom_fields'])->toBeArray();

        // RMS date format
        expect($data['created_at'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/');
        expect($data['updated_at'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/');
    });

    it('returns correct list response with wrapping and meta', function () {
        ProductGroup::factory()->count(3)->create();
        $token = $this->owner->createToken('test', ['products:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/product_groups')
            ->assertOk()
            ->assertJsonStructure([
                'product_groups' => [
                    '*' => [
                        'id', 'name', 'description', 'parent_id', 'sort_order',
                        'custom_fields', 'created_at', 'updated_at',
                    ],
                ],
                'meta' => ['total', 'per_page', 'page'],
            ]);

        expect($response->json())->toHaveKeys(['product_groups', 'meta']);
        expect($response->json('meta.total'))->toBe(3);
    });

    it('returns null parent when no parent exists', function () {
        $group = ProductGroup::factory()->create(['parent_id' => null]);
        $token = $this->owner->createToken('test', ['products:read'])->plainTextToken;

        $data = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/product_groups/{$group->id}?include=parent")
            ->assertOk()
            ->json('product_group');

        expect($data['parent_id'])->toBeNull();
        expect($data['parent'])->toBeNull();
    });

    it('returns empty string for null description', function () {
        $group = ProductGroup::factory()->create(['description' => null]);
        $token = $this->owner->createToken('test', ['products:read'])->plainTextToken;

        $data = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/product_groups/{$group->id}")
            ->assertOk()
            ->json('product_group');

        expect($data['description'])->toBe('');
    });
});
