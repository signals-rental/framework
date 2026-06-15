<?php

use App\Models\ListName;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->token = $this->owner->createToken('test', ['static-data:write'])->plainTextToken;
});

it('rejects renaming a list name to one that already exists', function () {
    ListName::factory()->create(['name' => 'Existing List']);
    $target = ListName::factory()->create(['name' => 'Target List']);

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->putJson("/api/v1/list_names/{$target->id}", [
            'name' => 'Existing List',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

it('allows saving a list name with its own unchanged name', function () {
    $listName = ListName::factory()->create(['name' => 'Stable List']);

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->putJson("/api/v1/list_names/{$listName->id}", [
            'name' => 'Stable List',
            'description' => 'Updated description, same name.',
        ])
        ->assertOk()
        ->assertJsonPath('list_name.name', 'Stable List')
        ->assertJsonPath('list_name.description', 'Updated description, same name.');
});

it('allows renaming a list name to a genuinely new value', function () {
    $listName = ListName::factory()->create(['name' => 'Before']);

    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->putJson("/api/v1/list_names/{$listName->id}", [
            'name' => 'After',
        ])
        ->assertOk()
        ->assertJsonPath('list_name.name', 'After');
});
