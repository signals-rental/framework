<?php

use App\Actions\TaxClasses\CreateOrganisationTaxClass;
use App\Actions\TaxClasses\CreateProductTaxClass;
use App\Actions\TaxClasses\DeleteOrganisationTaxClass;
use App\Actions\TaxClasses\DeleteProductTaxClass;
use App\Actions\TaxClasses\UpdateOrganisationTaxClass;
use App\Actions\TaxClasses\UpdateProductTaxClass;
use App\Data\TaxClasses\CreateTaxClassData;
use App\Data\TaxClasses\UpdateTaxClassData;
use App\Events\AuditableEvent;
use App\Models\OrganisationTaxClass;
use App\Models\ProductTaxClass;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

// Organisation Tax Classes

it('creates an organisation tax class', function () {
    Event::fake([AuditableEvent::class]);

    $data = CreateTaxClassData::from([
        'name' => 'VAT Registered',
        'description' => 'Standard VAT rate applies',
        'is_default' => false,
    ]);

    $result = (new CreateOrganisationTaxClass)($data);

    expect($result->name)->toBe('VAT Registered');
    expect($result->description)->toBe('Standard VAT rate applies');
    expect(OrganisationTaxClass::where('name', 'VAT Registered')->exists())->toBeTrue();

    Event::assertDispatched(AuditableEvent::class);
});

it('updates an organisation tax class', function () {
    Event::fake([AuditableEvent::class]);

    $taxClass = OrganisationTaxClass::factory()->create(['name' => 'Old']);

    $data = UpdateTaxClassData::from(['name' => 'Updated']);

    $result = (new UpdateOrganisationTaxClass)($taxClass, $data);

    expect($result->name)->toBe('Updated');

    Event::assertDispatched(AuditableEvent::class);
});

it('deletes an organisation tax class', function () {
    Event::fake([AuditableEvent::class]);

    $taxClass = OrganisationTaxClass::factory()->create();

    (new DeleteOrganisationTaxClass)($taxClass);

    expect(OrganisationTaxClass::find($taxClass->id))->toBeNull();

    Event::assertDispatched(AuditableEvent::class);
});

it('rejects unauthorized organisation tax class creation', function () {
    $regularUser = User::factory()->create();
    $this->actingAs($regularUser);

    $data = CreateTaxClassData::from(['name' => 'Unauthorized']);

    (new CreateOrganisationTaxClass)($data);
})->throws(AuthorizationException::class);

// Product Tax Classes

it('creates a product tax class', function () {
    Event::fake([AuditableEvent::class]);

    $data = CreateTaxClassData::from([
        'name' => 'Exempt',
        'description' => 'No tax applies',
        'is_default' => false,
    ]);

    $result = (new CreateProductTaxClass)($data);

    expect($result->name)->toBe('Exempt');
    expect(ProductTaxClass::where('name', 'Exempt')->exists())->toBeTrue();

    Event::assertDispatched(AuditableEvent::class);
});

it('updates a product tax class', function () {
    Event::fake([AuditableEvent::class]);

    $taxClass = ProductTaxClass::factory()->create(['name' => 'Old']);

    $data = UpdateTaxClassData::from(['name' => 'Updated']);

    $result = (new UpdateProductTaxClass)($taxClass, $data);

    expect($result->name)->toBe('Updated');

    Event::assertDispatched(AuditableEvent::class);
});

it('deletes a product tax class', function () {
    Event::fake([AuditableEvent::class]);

    $taxClass = ProductTaxClass::factory()->create();

    (new DeleteProductTaxClass)($taxClass);

    expect(ProductTaxClass::find($taxClass->id))->toBeNull();

    Event::assertDispatched(AuditableEvent::class);
});

it('rejects unauthorized product tax class creation', function () {
    $regularUser = User::factory()->create();
    $this->actingAs($regularUser);

    $data = CreateTaxClassData::from(['name' => 'Unauthorized']);

    (new CreateProductTaxClass)($data);
})->throws(AuthorizationException::class);
