<?php

use App\Models\OrganisationTaxClass;
use App\Models\ProductTaxClass;
use App\Models\TaxRate;
use App\Models\TaxRule;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

it('lists tax rules', function () {
    TaxRule::factory()->count(2)->create();
    $token = $this->owner->createToken('test', ['tax-classes:read'])->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/tax_rules')
        ->assertOk()
        ->assertJsonStructure([
            'tax_rules' => [
                '*' => ['id', 'organisation_tax_class_id', 'product_tax_class_id', 'tax_rate_id', 'priority', 'is_active'],
            ],
            'meta',
        ]);
});

it('shows a single tax rule', function () {
    $taxRule = TaxRule::factory()->create();
    $token = $this->owner->createToken('test', ['tax-classes:read'])->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/tax_rules/{$taxRule->id}")
        ->assertOk()
        ->assertJsonPath('tax_rule.id', $taxRule->id);
});

it('creates a tax rule', function () {
    $orgClass = OrganisationTaxClass::factory()->create();
    $prodClass = ProductTaxClass::factory()->create();
    $taxRate = TaxRate::factory()->create();
    $token = $this->owner->createToken('test', ['tax-classes:write'])->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/tax_rules', [
            'organisation_tax_class_id' => $orgClass->id,
            'product_tax_class_id' => $prodClass->id,
            'tax_rate_id' => $taxRate->id,
            'priority' => 1,
        ])
        ->assertCreated()
        ->assertJsonPath('tax_rule.tax_rate_id', $taxRate->id);
});

it('updates a tax rule', function () {
    $taxRule = TaxRule::factory()->create(['priority' => 0]);
    $token = $this->owner->createToken('test', ['tax-classes:write'])->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson("/api/v1/tax_rules/{$taxRule->id}", [
            'priority' => 5,
        ])
        ->assertOk()
        ->assertJsonPath('tax_rule.priority', 5);
});

it('deletes a tax rule', function () {
    $taxRule = TaxRule::factory()->create();
    $token = $this->owner->createToken('test', ['tax-classes:write'])->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/v1/tax_rules/{$taxRule->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('tax_rules', ['id' => $taxRule->id]);
});

it('requires tax-classes:read ability', function () {
    $token = $this->owner->createToken('test', ['members:read'])->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/tax_rules')
        ->assertForbidden();
});
