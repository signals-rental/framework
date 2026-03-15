<?php

use App\Actions\CustomFields\CreateCustomField;
use App\Actions\CustomFields\DeleteCustomField;
use App\Actions\CustomFields\UpdateCustomField;
use App\Data\CustomFields\CreateCustomFieldData;
use App\Data\CustomFields\UpdateCustomFieldData;
use App\Enums\CustomFieldType;
use App\Events\AuditableEvent;
use App\Models\CustomField;
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

it('creates a custom field', function () {
    Event::fake([AuditableEvent::class]);

    $data = CreateCustomFieldData::from([
        'name' => 'po_reference',
        'module_type' => 'Member',
        'field_type' => CustomFieldType::String->value,
        'display_name' => 'PO Reference',
        'is_required' => true,
    ]);

    $result = (new CreateCustomField)($data);

    expect($result->name)->toBe('po_reference');
    expect($result->module_type)->toBe('Member');
    expect($result->field_type)->toBe(CustomFieldType::String->value);
    expect($result->is_required)->toBeTrue();
    expect(CustomField::where('name', 'po_reference')->exists())->toBeTrue();

    Event::assertDispatched(AuditableEvent::class);
});

it('updates a custom field', function () {
    Event::fake([AuditableEvent::class]);

    $field = CustomField::factory()->string()->forModule('Member')->create();

    $data = UpdateCustomFieldData::from(['display_name' => 'Updated Name']);

    $result = (new UpdateCustomField)($field, $data);

    expect($result->display_name)->toBe('Updated Name');

    Event::assertDispatched(AuditableEvent::class);
});

it('deletes a custom field', function () {
    Event::fake([AuditableEvent::class]);

    $field = CustomField::factory()->string()->forModule('Member')->create();

    (new DeleteCustomField)($field);

    expect(CustomField::find($field->id))->toBeNull();

    Event::assertDispatched(AuditableEvent::class);
});

it('rejects unauthorized custom field creation', function () {
    $regularUser = User::factory()->create();
    $this->actingAs($regularUser);

    $data = CreateCustomFieldData::from([
        'name' => 'unauthorized_field',
        'module_type' => 'Member',
        'field_type' => CustomFieldType::String->value,
    ]);

    (new CreateCustomField)($data);
})->throws(AuthorizationException::class);
