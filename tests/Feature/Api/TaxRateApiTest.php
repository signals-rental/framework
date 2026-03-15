<?php

use App\Models\TaxRate;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

it('lists tax rates', function () {
    TaxRate::factory()->count(2)->create();
    $token = $this->owner->createToken('test', ['tax-classes:read'])->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/tax_rates')
        ->assertOk()
        ->assertJsonStructure([
            'tax_rates' => [
                '*' => ['id', 'name', 'rate', 'is_active', 'created_at'],
            ],
            'meta',
        ]);
});

it('shows a single tax rate', function () {
    $taxRate = TaxRate::factory()->create(['name' => 'Standard', 'rate' => '20.0000']);
    $token = $this->owner->createToken('test', ['tax-classes:read'])->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/tax_rates/{$taxRate->id}")
        ->assertOk()
        ->assertJsonPath('tax_rate.name', 'Standard');
});

it('creates a tax rate', function () {
    $token = $this->owner->createToken('test', ['tax-classes:write'])->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/tax_rates', [
            'name' => 'Reduced',
            'rate' => '5.0000',
        ])
        ->assertCreated()
        ->assertJsonPath('tax_rate.name', 'Reduced');
});

it('updates a tax rate', function () {
    $taxRate = TaxRate::factory()->create(['name' => 'Old']);
    $token = $this->owner->createToken('test', ['tax-classes:write'])->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson("/api/v1/tax_rates/{$taxRate->id}", [
            'name' => 'Updated',
        ])
        ->assertOk()
        ->assertJsonPath('tax_rate.name', 'Updated');
});

it('deletes a tax rate', function () {
    $taxRate = TaxRate::factory()->create();
    $token = $this->owner->createToken('test', ['tax-classes:write'])->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/v1/tax_rates/{$taxRate->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('tax_rates', ['id' => $taxRate->id]);
});

it('requires tax-classes:read ability', function () {
    $token = $this->owner->createToken('test', ['members:read'])->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/tax_rates')
        ->assertForbidden();
});
