<?php

use App\Actions\CustomFields\CreateCustomField;
use App\Actions\CustomFields\DeleteCustomField;
use App\Actions\CustomFields\UpdateCustomField;
use App\Data\CustomFields\CreateCustomFieldData;
use App\Data\CustomFields\UpdateCustomFieldData;
use App\Enums\CustomFieldType;
use App\Events\AuditableEvent;
use App\Models\CustomField;
use App\Models\Member;
use App\Models\User;
use App\Services\SchemaRegistry;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

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

it('sets and changes validation and visibility rules on update', function () {
    $field = CustomField::factory()->string()->forModule('Member')->create([
        'validation_rules' => null,
        'visibility_rules' => null,
    ]);

    // Set rules where there were none.
    (new UpdateCustomField)($field, UpdateCustomFieldData::from([
        'validation_rules' => ['max_length' => 20],
        'visibility_rules' => [['field' => 'status', 'operator' => 'eq', 'value' => 'active']],
    ]));

    $field->refresh();
    expect($field->validation_rules)->toBe(['max_length' => 20]);
    expect($field->visibility_rules)->toBe([['field' => 'status', 'operator' => 'eq', 'value' => 'active']]);

    // Change the existing rules.
    (new UpdateCustomField)($field, UpdateCustomFieldData::from([
        'validation_rules' => ['min_length' => 3, 'max_length' => 50],
        'visibility_rules' => [['field' => 'status', 'operator' => 'eq', 'value' => 'archived']],
    ]));

    $field->refresh();
    expect($field->validation_rules)->toBe(['min_length' => 3, 'max_length' => 50]);
    expect($field->visibility_rules)->toBe([['field' => 'status', 'operator' => 'eq', 'value' => 'archived']]);
});

it('clears validation and visibility rules when explicitly set to null on update', function () {
    // #204: an explicit null must persist as a clear, not be silently dropped.
    $field = CustomField::factory()->string()->forModule('Member')->create([
        'validation_rules' => ['max_length' => 20],
        'visibility_rules' => [['field' => 'status', 'operator' => 'eq', 'value' => 'active']],
    ]);

    (new UpdateCustomField)($field, UpdateCustomFieldData::from([
        'validation_rules' => null,
        'visibility_rules' => null,
    ]));

    $field->refresh();
    expect($field->validation_rules)->toBeNull();
    expect($field->visibility_rules)->toBeNull();
});

it('clears validation and visibility rules when explicitly set to empty arrays on update', function () {
    $field = CustomField::factory()->string()->forModule('Member')->create([
        'validation_rules' => ['max_length' => 20],
        'visibility_rules' => [['field' => 'status', 'operator' => 'eq', 'value' => 'active']],
    ]);

    (new UpdateCustomField)($field, UpdateCustomFieldData::from([
        'validation_rules' => [],
        'visibility_rules' => [],
    ]));

    $field->refresh();
    expect($field->validation_rules)->toBe([]);
    expect($field->visibility_rules)->toBe([]);
});

it('leaves omitted fields unchanged on update (partial update)', function () {
    $field = CustomField::factory()->string()->forModule('Member')->create([
        'display_name' => 'Original Display',
        'description' => 'Original description',
        'validation_rules' => ['max_length' => 20],
        'visibility_rules' => [['field' => 'status', 'operator' => 'eq', 'value' => 'active']],
        'is_required' => true,
    ]);

    // Only touch display_name; everything else is omitted and must be preserved.
    (new UpdateCustomField)($field, UpdateCustomFieldData::from([
        'display_name' => 'New Display',
    ]));

    $field->refresh();
    expect($field->display_name)->toBe('New Display');
    expect($field->description)->toBe('Original description');
    expect($field->validation_rules)->toBe(['max_length' => 20]);
    expect($field->visibility_rules)->toBe([['field' => 'status', 'operator' => 'eq', 'value' => 'active']]);
    expect($field->is_required)->toBeTrue();
});

it('rejects renaming a custom field to a name already used in the same module', function () {
    // #210: a duplicate rename must fail validation (422), not raise an
    // uncaught QueryException from the ['name', 'module_type'] unique index (500).
    CustomField::factory()->string()->forModule('Member')->create(['name' => 'existing_field']);
    $field = CustomField::factory()->string()->forModule('Member')->create(['name' => 'original_field']);

    try {
        (new UpdateCustomField)($field, UpdateCustomFieldData::from(['name' => 'existing_field']));
        $this->fail('Expected a ValidationException for the duplicate rename.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('name');
    }

    // The field must be untouched after the failed rename.
    $field->refresh();
    expect($field->name)->toBe('original_field');
})->throws(ValidationException::class);

it('allows renaming a custom field to a name used in a different module', function () {
    // The unique index is scoped to module_type, so the same name may exist
    // under another module without conflict.
    CustomField::factory()->string()->forModule('Product')->create(['name' => 'shared_name']);
    $field = CustomField::factory()->string()->forModule('Member')->create(['name' => 'original_field']);

    $result = (new UpdateCustomField)($field, UpdateCustomFieldData::from(['name' => 'shared_name']));

    expect($result->name)->toBe('shared_name');
});

it('allows renaming a custom field to its own current name', function () {
    // ignore() excludes the current record, so a no-op rename still succeeds.
    $field = CustomField::factory()->string()->forModule('Member')->create([
        'name' => 'po_reference',
        'display_name' => 'Original',
    ]);

    $result = (new UpdateCustomField)($field, UpdateCustomFieldData::from([
        'name' => 'po_reference',
        'display_name' => 'Updated',
    ]));

    expect($result->name)->toBe('po_reference');
    expect($result->display_name)->toBe('Updated');
});

it('deletes a custom field', function () {
    Event::fake([AuditableEvent::class]);

    $field = CustomField::factory()->string()->forModule('Member')->create();

    (new DeleteCustomField)($field);

    expect(CustomField::find($field->id))->toBeNull();

    Event::assertDispatched(AuditableEvent::class);
});

it('invalidates the cached model schema when a custom field is created', function () {
    // Prime the Member schema cache while the field is absent.
    expect(app(SchemaRegistry::class)->resolve(Member::class))->not->toHaveKey('loyalty_tier');

    (new CreateCustomField)(CreateCustomFieldData::from([
        'name' => 'loyalty_tier',
        'module_type' => 'Member',
        'field_type' => CustomFieldType::String->value,
        'display_name' => 'Loyalty Tier',
    ]));

    // The new field is visible immediately — the L2 schema cache was invalidated.
    expect(app(SchemaRegistry::class)->resolve(Member::class))->toHaveKey('loyalty_tier');
});

it('invalidates the cached model schema when a custom field is deleted', function () {
    $field = CustomField::factory()->string()->forModule('Member')->create(['name' => 'temp_code']);

    // Prime the cache with the field present.
    expect(app(SchemaRegistry::class)->resolve(Member::class))->toHaveKey('temp_code');

    (new DeleteCustomField)($field);

    expect(app(SchemaRegistry::class)->resolve(Member::class))->not->toHaveKey('temp_code');
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
