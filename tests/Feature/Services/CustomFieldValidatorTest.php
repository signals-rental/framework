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

it('generates correct rules for a website field', function () {
    CustomField::factory()->website()->forModule('Opportunity')->create([
        'name' => 'company_url',
    ]);

    $rules = $this->validator->rules('Opportunity', ['company_url' => 'https://example.com']);

    expect($rules)->toHaveKey('company_url')
        ->and($rules['company_url'])->toContain('nullable', 'url');
});

it('generates correct rules for a telephone field', function () {
    CustomField::factory()->telephone()->forModule('Opportunity')->create([
        'name' => 'phone_number',
    ]);

    $rules = $this->validator->rules('Opportunity', ['phone_number' => '+44 7700 900000']);

    expect($rules)->toHaveKey('phone_number')
        ->and($rules['phone_number'])->toContain('nullable', 'string');
});

it('generates correct rules for a currency field with min and max', function () {
    CustomField::factory()->currency()->forModule('Opportunity')->create([
        'name' => 'budget',
        'validation_rules' => ['min' => 0, 'max' => 999999],
    ]);

    $rules = $this->validator->rules('Opportunity', ['budget' => 500]);

    expect($rules)->toHaveKey('budget')
        ->and($rules['budget'])->toContain('nullable', 'numeric', 'min:0', 'max:999999');
});

it('generates correct rules for a percentage field', function () {
    CustomField::factory()->percentage()->forModule('Opportunity')->create([
        'name' => 'discount_rate',
        'validation_rules' => ['min' => 0, 'max' => 100],
    ]);

    $rules = $this->validator->rules('Opportunity', ['discount_rate' => 15]);

    expect($rules)->toHaveKey('discount_rate')
        ->and($rules['discount_rate'])->toContain('nullable', 'numeric', 'min:0', 'max:100');
});

it('generates correct rules for a time field', function () {
    CustomField::factory()->forModule('Opportunity')->create([
        'name' => 'start_time',
        'field_type' => \App\Enums\CustomFieldType::Time,
    ]);

    $rules = $this->validator->rules('Opportunity', ['start_time' => '14:30:00']);

    expect($rules)->toHaveKey('start_time')
        ->and($rules['start_time'])->toContain('nullable', 'date_format:H:i:s');
});

it('generates correct rules for a datetime field', function () {
    CustomField::factory()->forModule('Opportunity')->create([
        'name' => 'event_start',
        'field_type' => \App\Enums\CustomFieldType::DateTime,
    ]);

    $rules = $this->validator->rules('Opportunity', ['event_start' => '2026-01-15 14:30:00']);

    expect($rules)->toHaveKey('event_start')
        ->and($rules['event_start'])->toContain('nullable', 'date');
});

it('generates correct rules for a rich text field', function () {
    CustomField::factory()->richText()->forModule('Opportunity')->create([
        'name' => 'notes',
        'validation_rules' => ['max_length' => 5000],
    ]);

    $rules = $this->validator->rules('Opportunity', ['notes' => '<p>Hello</p>']);

    expect($rules)->toHaveKey('notes')
        ->and($rules['notes'])->toContain('nullable', 'string', 'max:5000');
});

it('generates correct rules for a text field', function () {
    CustomField::factory()->text()->forModule('Opportunity')->create([
        'name' => 'description',
        'validation_rules' => ['max_length' => 10000],
    ]);

    $rules = $this->validator->rules('Opportunity', ['description' => 'Some text']);

    expect($rules)->toHaveKey('description')
        ->and($rules['description'])->toContain('nullable', 'string', 'max:10000');
});

it('generates correct rules for a json key-value field', function () {
    CustomField::factory()->forModule('Opportunity')->create([
        'name' => 'metadata',
        'field_type' => \App\Enums\CustomFieldType::JsonKeyValue,
    ]);

    $rules = $this->validator->rules('Opportunity', ['metadata' => ['key' => 'value']]);

    expect($rules)->toHaveKey('metadata')
        ->and($rules['metadata'])->toContain('nullable', 'array');
});

it('generates correct rules for a colour field with pattern', function () {
    CustomField::factory()->colour()->forModule('Opportunity')->create([
        'name' => 'brand_colour',
        'validation_rules' => ['pattern' => '/^#[0-9A-Fa-f]{6}$/'],
    ]);

    $rules = $this->validator->rules('Opportunity', ['brand_colour' => '#FF5500']);

    expect($rules)->toHaveKey('brand_colour')
        ->and($rules['brand_colour'])->toContain('nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/');
});

it('returns null rules for AutoNumber field type', function () {
    CustomField::factory()->autoNumber()->forModule('Opportunity')->create([
        'name' => 'auto_ref',
    ]);

    $rules = $this->validator->rules('Opportunity', ['auto_ref' => 'AN-001']);

    expect($rules)->not->toHaveKey('auto_ref');
});

it('returns null rules for FileImage field type', function () {
    CustomField::factory()->forModule('Opportunity')->create([
        'name' => 'photo',
        'field_type' => \App\Enums\CustomFieldType::FileImage,
    ]);

    $rules = $this->validator->rules('Opportunity', ['photo' => 'some-file']);

    expect($rules)->not->toHaveKey('photo');
});

it('rejects invalid values in MultiListOfValues field', function () {
    $listName = ListName::factory()->create();
    ListValue::factory()->forList($listName)->create(['name' => 'Red']);

    CustomField::factory()->multiListOfValues()->forModule('Opportunity')->create([
        'name' => 'colours',
        'list_name_id' => $listName->id,
    ]);

    $this->validator->validate('Opportunity', ['colours' => ['Red', 'InvalidColour']]);
})->throws(ValidationException::class);

it('generates rules for currency field without min/max', function () {
    CustomField::factory()->currency()->forModule('Opportunity')->create([
        'name' => 'price',
        'validation_rules' => [],
    ]);

    $rules = $this->validator->rules('Opportunity', ['price' => 100]);

    expect($rules)->toHaveKey('price')
        ->and($rules['price'])->toContain('nullable', 'numeric')
        ->and($rules['price'])->not->toContain('min:0');
});

it('generates rules for colour field without pattern', function () {
    CustomField::factory()->colour()->forModule('Opportunity')->create([
        'name' => 'tint',
        'validation_rules' => [],
    ]);

    $rules = $this->validator->rules('Opportunity', ['tint' => '#000000']);

    expect($rules)->toHaveKey('tint')
        ->and($rules['tint'])->toContain('nullable', 'string')
        ->and($rules['tint'])->toHaveCount(2);
});

it('generates rules for text field without max_length', function () {
    CustomField::factory()->text()->forModule('Opportunity')->create([
        'name' => 'plain_notes',
        'validation_rules' => [],
    ]);

    $rules = $this->validator->rules('Opportunity', ['plain_notes' => 'Hello']);

    expect($rules)->toHaveKey('plain_notes')
        ->and($rules['plain_notes'])->toContain('nullable', 'string')
        ->and($rules['plain_notes'])->toHaveCount(2);
});

it('generates list of values rules without list_name_id', function () {
    CustomField::factory()->forModule('Opportunity')->create([
        'name' => 'orphan_list',
        'field_type' => \App\Enums\CustomFieldType::ListOfValues,
        'list_name_id' => null,
    ]);

    $rules = $this->validator->rules('Opportunity', ['orphan_list' => 'anything']);

    expect($rules)->toHaveKey('orphan_list')
        ->and($rules['orphan_list'])->toContain('nullable');
});

it('generates multi list of values rules without list_name_id', function () {
    CustomField::factory()->forModule('Opportunity')->create([
        'name' => 'orphan_multi',
        'field_type' => \App\Enums\CustomFieldType::MultiListOfValues,
        'list_name_id' => null,
    ]);

    $rules = $this->validator->rules('Opportunity', ['orphan_multi' => ['a']]);

    expect($rules)->toHaveKey('orphan_multi')
        ->and($rules['orphan_multi'])->toContain('nullable', 'array');
});

it('throws ValidationException for invalid data', function () {
    CustomField::factory()->email()->required()->forModule('Opportunity')->create([
        'name' => 'billing_email',
    ]);

    $this->validator->validate('Opportunity', ['billing_email' => 'not-an-email']);
})->throws(ValidationException::class);
