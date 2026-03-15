<?php

use App\Models\CustomField;
use App\Models\ListName;
use App\Models\ListValue;
use App\Services\CustomFieldValidator;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->validator = app(CustomFieldValidator::class);
});

it('generates correct rules for a string field', function () {
    CustomField::factory()->string()->forModule('Opportunity')->create([
        'name' => 'po_reference',
        'validation_rules' => ['min_length' => 2, 'max_length' => 50, 'pattern' => '/^PO-/'],
    ]);

    $rules = $this->validator->rules('Opportunity', ['po_reference' => 'PO-123']);

    expect($rules)->toHaveKey('po_reference')
        ->and($rules['po_reference'])->toContain('nullable', 'string', 'min:2', 'max:50', 'regex:/^PO-/');
});

it('generates correct rules for a number field with min and max', function () {
    CustomField::factory()->number()->forModule('Opportunity')->create([
        'name' => 'weight',
        'validation_rules' => ['min' => 0, 'max' => 1000],
    ]);

    $rules = $this->validator->rules('Opportunity', ['weight' => 50]);

    expect($rules)->toHaveKey('weight')
        ->and($rules['weight'])->toContain('nullable', 'numeric', 'min:0', 'max:1000');
});

it('generates correct rules for a boolean field', function () {
    CustomField::factory()->boolean()->forModule('Opportunity')->create([
        'name' => 'is_fragile',
    ]);

    $rules = $this->validator->rules('Opportunity', ['is_fragile' => true]);

    expect($rules)->toHaveKey('is_fragile')
        ->and($rules['is_fragile'])->toContain('nullable', 'boolean');
});

it('generates correct rules for a date field', function () {
    CustomField::factory()->date()->forModule('Opportunity')->create([
        'name' => 'delivery_date',
    ]);

    $rules = $this->validator->rules('Opportunity', ['delivery_date' => '2026-01-15']);

    expect($rules)->toHaveKey('delivery_date')
        ->and($rules['delivery_date'])->toContain('nullable', 'date_format:Y-m-d');
});

it('generates correct rules for an email field', function () {
    CustomField::factory()->email()->forModule('Opportunity')->create([
        'name' => 'contact_email',
    ]);

    $rules = $this->validator->rules('Opportunity', ['contact_email' => 'test@example.com']);

    expect($rules)->toHaveKey('contact_email')
        ->and($rules['contact_email'])->toContain('nullable', 'email');
});

it('prepends required for required fields', function () {
    CustomField::factory()->string()->required()->forModule('Opportunity')->create([
        'name' => 'mandatory_ref',
    ]);

    $rules = $this->validator->rules('Opportunity', ['mandatory_ref' => 'value']);

    expect($rules['mandatory_ref'][0])->toBe('required');
});

it('prepends nullable for optional fields', function () {
    CustomField::factory()->string()->forModule('Opportunity')->create([
        'name' => 'optional_ref',
        'is_required' => false,
    ]);

    $rules = $this->validator->rules('Opportunity', ['optional_ref' => null]);

    expect($rules['optional_ref'][0])->toBe('nullable');
});

it('validates string value exists in list values for ListOfValues field', function () {
    $listName = ListName::factory()->create();
    ListValue::factory()->forList($listName)->create(['name' => 'Small']);
    ListValue::factory()->forList($listName)->create(['name' => 'Medium']);
    ListValue::factory()->forList($listName)->create(['name' => 'Large']);

    CustomField::factory()->listOfValues()->forModule('Opportunity')->create([
        'name' => 'size',
        'list_name_id' => $listName->id,
    ]);

    $rules = $this->validator->rules('Opportunity', ['size' => 'Medium']);

    expect($rules)->toHaveKey('size');

    // Validate passes for a valid name
    $result = $this->validator->validate('Opportunity', ['size' => 'Medium']);
    expect($result)->toHaveKey('size')
        ->and($result['size'])->toBe('Medium');
});

it('validates integer ID exists in list values for ListOfValues field', function () {
    $listName = ListName::factory()->create();
    $value = ListValue::factory()->forList($listName)->create(['name' => 'Option A']);

    CustomField::factory()->listOfValues()->forModule('Opportunity')->create([
        'name' => 'category',
        'list_name_id' => $listName->id,
    ]);

    $result = $this->validator->validate('Opportunity', ['category' => $value->id]);
    expect($result)->toHaveKey('category')
        ->and($result['category'])->toBe($value->id);
});

it('validates array of valid values for MultiListOfValues field', function () {
    $listName = ListName::factory()->create();
    ListValue::factory()->forList($listName)->create(['name' => 'Red']);
    ListValue::factory()->forList($listName)->create(['name' => 'Blue']);
    ListValue::factory()->forList($listName)->create(['name' => 'Green']);

    CustomField::factory()->multiListOfValues()->forModule('Opportunity')->create([
        'name' => 'colours',
        'list_name_id' => $listName->id,
    ]);

    $result = $this->validator->validate('Opportunity', ['colours' => ['Red', 'Blue']]);
    expect($result)->toHaveKey('colours')
        ->and($result['colours'])->toBe(['Red', 'Blue']);
});

it('ignores unknown field names not in definitions', function () {
    CustomField::factory()->string()->forModule('Opportunity')->create([
        'name' => 'known_field',
    ]);

    $rules = $this->validator->rules('Opportunity', [
        'known_field' => 'value',
        'unknown_field' => 'other',
    ]);

    expect($rules)->toHaveKey('known_field')
        ->and($rules)->not->toHaveKey('unknown_field');
});

it('ignores inactive fields', function () {
    CustomField::factory()->string()->inactive()->forModule('Opportunity')->create([
        'name' => 'retired_field',
    ]);

    $rules = $this->validator->rules('Opportunity', ['retired_field' => 'value']);

    expect($rules)->not->toHaveKey('retired_field');
});

it('returns validated data from validate method', function () {
    CustomField::factory()->string()->forModule('Opportunity')->create([
        'name' => 'ref_code',
    ]);
    CustomField::factory()->number()->forModule('Opportunity')->create([
        'name' => 'quantity',
    ]);

    $result = $this->validator->validate('Opportunity', [
        'ref_code' => 'ABC-123',
        'quantity' => 42,
    ]);

    expect($result)->toBe([
        'ref_code' => 'ABC-123',
        'quantity' => 42,
    ]);
});

it('throws ValidationException for invalid data', function () {
    CustomField::factory()->email()->required()->forModule('Opportunity')->create([
        'name' => 'billing_email',
    ]);

    $this->validator->validate('Opportunity', ['billing_email' => 'not-an-email']);
})->throws(ValidationException::class);
