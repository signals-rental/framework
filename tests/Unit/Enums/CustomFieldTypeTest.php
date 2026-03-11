<?php

use App\Enums\CustomFieldType;

it('has a label for every case', function () {
    foreach (CustomFieldType::cases() as $type) {
        expect($type->label())->toBeString()->not()->toBeEmpty();
    }
});

it('returns human-readable labels', function (CustomFieldType $type, string $expected) {
    expect($type->label())->toBe($expected);
})->with([
    'Text' => [CustomFieldType::Text, 'Text'],
    'TextArea' => [CustomFieldType::TextArea, 'Text Area'],
    'DateTime' => [CustomFieldType::DateTime, 'Date & Time'],
    'MultiSelect' => [CustomFieldType::MultiSelect, 'Multi Select'],
    'Url' => [CustomFieldType::Url, 'URL'],
    'RichText' => [CustomFieldType::RichText, 'Rich Text'],
]);

it('has a value column for every case', function () {
    foreach (CustomFieldType::cases() as $type) {
        expect($type->valueColumn())->toBeString()->toStartWith('value_');
    }
});

it('maps field types to correct value columns', function (CustomFieldType $type, string $expected) {
    expect($type->valueColumn())->toBe($expected);
})->with([
    'Text → string' => [CustomFieldType::Text, 'value_string'],
    'TextArea → text' => [CustomFieldType::TextArea, 'value_text'],
    'Integer → integer' => [CustomFieldType::Integer, 'value_integer'],
    'Decimal → decimal' => [CustomFieldType::Decimal, 'value_decimal'],
    'Boolean → boolean' => [CustomFieldType::Boolean, 'value_boolean'],
    'Date → date' => [CustomFieldType::Date, 'value_date'],
    'DateTime → datetime' => [CustomFieldType::DateTime, 'value_datetime'],
    'Time → time' => [CustomFieldType::Time, 'value_time'],
    'Select → string' => [CustomFieldType::Select, 'value_string'],
    'MultiSelect → json' => [CustomFieldType::MultiSelect, 'value_json'],
    'Currency → decimal' => [CustomFieldType::Currency, 'value_decimal'],
    'Percentage → decimal' => [CustomFieldType::Percentage, 'value_decimal'],
    'RichText → text' => [CustomFieldType::RichText, 'value_text'],
    'Url → string' => [CustomFieldType::Url, 'value_string'],
    'Email → string' => [CustomFieldType::Email, 'value_string'],
    'Phone → string' => [CustomFieldType::Phone, 'value_string'],
    'Colour → string' => [CustomFieldType::Colour, 'value_string'],
]);
