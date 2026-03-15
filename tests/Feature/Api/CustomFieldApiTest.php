<?php

use App\Enums\CustomFieldType;
use App\Models\CustomField;
use App\Models\CustomFieldGroup;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

describe('Custom Field Groups API', function () {
    it('lists custom field groups', function () {
        CustomFieldGroup::factory()->count(2)->create();
        $token = $this->owner->createToken('test', ['custom-fields:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/custom_field_groups')
            ->assertOk()
            ->assertJsonStructure([
                'custom_field_groups' => [
                    '*' => ['id', 'name', 'sort_order', 'created_at'],
                ],
                'meta',
            ]);
    });

    it('creates a custom field group', function () {
        $token = $this->owner->createToken('test', ['custom-fields:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/custom_field_groups', [
                'name' => 'Contact Details',
                'sort_order' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('custom_field_group.name', 'Contact Details');

        $this->assertDatabaseHas('custom_field_groups', ['name' => 'Contact Details']);
    });

    it('updates a custom field group', function () {
        $group = CustomFieldGroup::factory()->create(['name' => 'Old']);
        $token = $this->owner->createToken('test', ['custom-fields:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/custom_field_groups/{$group->id}", [
                'name' => 'Updated',
            ])
            ->assertOk()
            ->assertJsonPath('custom_field_group.name', 'Updated');
    });

    it('deletes a custom field group', function () {
        $group = CustomFieldGroup::factory()->create();
        $token = $this->owner->createToken('test', ['custom-fields:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/custom_field_groups/{$group->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('custom_field_groups', ['id' => $group->id]);
    });

    it('shows a single custom field group', function () {
        $group = CustomFieldGroup::factory()->create(['name' => 'Shipping']);
        $token = $this->owner->createToken('test', ['custom-fields:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/custom_field_groups/{$group->id}")
            ->assertOk()
            ->assertJsonPath('custom_field_group.name', 'Shipping');
    });

    it('requires custom-fields:read ability for listing', function () {
        $token = $this->owner->createToken('test', ['members:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/custom_field_groups')
            ->assertForbidden();
    });
});

describe('Custom Fields API', function () {
    it('lists custom fields', function () {
        CustomField::factory()->count(2)->create();
        $token = $this->owner->createToken('test', ['custom-fields:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/custom_fields')
            ->assertOk()
            ->assertJsonStructure([
                'custom_fields' => [
                    '*' => ['id', 'name', 'module_type', 'field_type', 'is_active'],
                ],
                'meta',
            ]);
    });

    it('creates a custom field', function () {
        $token = $this->owner->createToken('test', ['custom-fields:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/custom_fields', [
                'name' => 'po_reference',
                'module_type' => 'member',
                'field_type' => CustomFieldType::String->value,
                'sort_order' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('custom_field.name', 'po_reference');
    });

    it('updates a custom field', function () {
        $field = CustomField::factory()->create();
        $token = $this->owner->createToken('test', ['custom-fields:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/custom_fields/{$field->id}", [
                'display_name' => 'Updated Display',
            ])
            ->assertOk()
            ->assertJsonPath('custom_field.display_name', 'Updated Display');
    });

    it('deletes a custom field', function () {
        $field = CustomField::factory()->create();
        $token = $this->owner->createToken('test', ['custom-fields:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/custom_fields/{$field->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('custom_fields', ['id' => $field->id]);
    });

    it('shows a single custom field', function () {
        $field = CustomField::factory()->create(['name' => 'po_ref']);
        $token = $this->owner->createToken('test', ['custom-fields:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/custom_fields/{$field->id}")
            ->assertOk()
            ->assertJsonPath('custom_field.name', 'po_ref');
    });

    it('filters by module_type', function () {
        CustomField::factory()->create(['module_type' => 'member']);
        CustomField::factory()->create(['module_type' => 'opportunity']);
        $token = $this->owner->createToken('test', ['custom-fields:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/custom_fields?q[module_type_eq]=member')
            ->assertOk();

        expect($response->json('custom_fields'))->toHaveCount(1);
    });
});
