<?php

use App\Models\ListName;
use App\Models\ListValue;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

describe('List Names API', function () {
    it('lists all list names', function () {
        ListName::factory()->count(2)->create();
        $token = $this->owner->createToken('test', ['static-data:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/list_names')
            ->assertOk()
            ->assertJsonStructure([
                'list_names' => [
                    '*' => ['id', 'name', 'is_system', 'created_at'],
                ],
                'meta',
            ]);
    });

    it('shows a list name with values', function () {
        $listName = ListName::factory()->create();
        ListValue::factory()->for($listName)->count(3)->create();
        $token = $this->owner->createToken('test', ['static-data:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/list_names/{$listName->id}")
            ->assertOk()
            ->assertJsonPath('list_name.name', $listName->name);

        expect($response->json('list_name.values'))->toHaveCount(3);
    });

    it('creates a list name', function () {
        $token = $this->owner->createToken('test', ['static-data:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/list_names', [
                'name' => 'Priority Level',
            ])
            ->assertCreated()
            ->assertJsonPath('list_name.name', 'Priority Level');
    });

    it('updates a list name', function () {
        $listName = ListName::factory()->create(['name' => 'Old']);
        $token = $this->owner->createToken('test', ['static-data:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/list_names/{$listName->id}", [
                'name' => 'Renamed',
            ])
            ->assertOk()
            ->assertJsonPath('list_name.name', 'Renamed');
    });

    it('deletes a list name', function () {
        $listName = ListName::factory()->create();
        $token = $this->owner->createToken('test', ['static-data:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/list_names/{$listName->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('list_names', ['id' => $listName->id]);
    });

    it('requires static-data:read ability', function () {
        $token = $this->owner->createToken('test', ['members:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/list_names')
            ->assertForbidden();
    });
});

describe('List Values API (nested)', function () {
    it('lists values for a list name', function () {
        $listName = ListName::factory()->create();
        ListValue::factory()->for($listName)->count(2)->create();
        $token = $this->owner->createToken('test', ['static-data:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/list_names/{$listName->id}/list_values")
            ->assertOk()
            ->assertJsonCount(2, 'list_values');
    });

    it('creates a value for a list name', function () {
        $listName = ListName::factory()->create();
        $token = $this->owner->createToken('test', ['static-data:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/list_names/{$listName->id}/list_values", [
                'name' => 'High',
                'sort_order' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('list_value.name', 'High');
    });

    it('updates a list value', function () {
        $listName = ListName::factory()->create();
        $value = ListValue::factory()->for($listName)->create(['name' => 'Old']);
        $token = $this->owner->createToken('test', ['static-data:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/list_names/{$listName->id}/list_values/{$value->id}", [
                'name' => 'Updated',
            ])
            ->assertOk()
            ->assertJsonPath('list_value.name', 'Updated');
    });

    it('deletes a list value', function () {
        $listName = ListName::factory()->create();
        $value = ListValue::factory()->for($listName)->create();
        $token = $this->owner->createToken('test', ['static-data:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/list_names/{$listName->id}/list_values/{$value->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('list_values', ['id' => $value->id]);
    });
});
