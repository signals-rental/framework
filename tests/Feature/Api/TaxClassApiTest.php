<?php

use App\Models\OrganisationTaxClass;
use App\Models\ProductTaxClass;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
});

describe('Organisation Tax Classes API', function () {
    it('lists organisation tax classes', function () {
        OrganisationTaxClass::factory()->count(2)->create();
        $token = $this->owner->createToken('test', ['tax-classes:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/organisation_tax_classes')
            ->assertOk()
            ->assertJsonStructure([
                'organisation_tax_classes' => [
                    '*' => ['id', 'name', 'is_default', 'created_at'],
                ],
                'meta',
            ]);
    });

    it('creates an organisation tax class', function () {
        $token = $this->owner->createToken('test', ['tax-classes:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/organisation_tax_classes', [
                'name' => 'Charity',
            ])
            ->assertCreated()
            ->assertJsonPath('organisation_tax_class.name', 'Charity');
    });

    it('updates an organisation tax class', function () {
        $taxClass = OrganisationTaxClass::factory()->create(['name' => 'Old']);
        $token = $this->owner->createToken('test', ['tax-classes:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/organisation_tax_classes/{$taxClass->id}", [
                'name' => 'Updated',
            ])
            ->assertOk()
            ->assertJsonPath('organisation_tax_class.name', 'Updated');
    });

    it('deletes an organisation tax class', function () {
        $taxClass = OrganisationTaxClass::factory()->create();
        $token = $this->owner->createToken('test', ['tax-classes:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/organisation_tax_classes/{$taxClass->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('organisation_tax_classes', ['id' => $taxClass->id]);
    });

    it('shows a single organisation tax class', function () {
        $taxClass = OrganisationTaxClass::factory()->create(['name' => 'Charity']);
        $token = $this->owner->createToken('test', ['tax-classes:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/organisation_tax_classes/{$taxClass->id}")
            ->assertOk()
            ->assertJsonPath('organisation_tax_class.name', 'Charity');
    });

    it('requires tax-classes:read ability', function () {
        $token = $this->owner->createToken('test', ['members:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/organisation_tax_classes')
            ->assertForbidden();
    });
});

describe('Product Tax Classes API', function () {
    it('lists product tax classes', function () {
        ProductTaxClass::factory()->count(2)->create();
        $token = $this->owner->createToken('test', ['tax-classes:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/product_tax_classes')
            ->assertOk()
            ->assertJsonStructure([
                'product_tax_classes' => [
                    '*' => ['id', 'name', 'is_default', 'created_at'],
                ],
                'meta',
            ]);
    });

    it('creates a product tax class', function () {
        $token = $this->owner->createToken('test', ['tax-classes:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/product_tax_classes', [
                'name' => 'Exempt',
            ])
            ->assertCreated()
            ->assertJsonPath('product_tax_class.name', 'Exempt');
    });

    it('updates a product tax class', function () {
        $taxClass = ProductTaxClass::factory()->create(['name' => 'Old']);
        $token = $this->owner->createToken('test', ['tax-classes:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/product_tax_classes/{$taxClass->id}", [
                'name' => 'Updated',
            ])
            ->assertOk()
            ->assertJsonPath('product_tax_class.name', 'Updated');
    });

    it('shows a single product tax class', function () {
        $taxClass = ProductTaxClass::factory()->create(['name' => 'Exempt']);
        $token = $this->owner->createToken('test', ['tax-classes:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/product_tax_classes/{$taxClass->id}")
            ->assertOk()
            ->assertJsonPath('product_tax_class.name', 'Exempt');
    });

    it('deletes a product tax class', function () {
        $taxClass = ProductTaxClass::factory()->create();
        $token = $this->owner->createToken('test', ['tax-classes:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/product_tax_classes/{$taxClass->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('product_tax_classes', ['id' => $taxClass->id]);
    });
});
