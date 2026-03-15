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
    'String' => [CustomFieldType::String, 'String'],
    'Text' => [CustomFieldType::Text, 'Text Area'],
    'Number' => [CustomFieldType::Number, 'Number'],
    'Boolean' => [CustomFieldType::Boolean, 'Boolean'],
    'DateTime' => [CustomFieldType::DateTime, 'Date & Time'],
    'Date' => [CustomFieldType::Date, 'Date'],
    'Time' => [CustomFieldType::Time, 'Time'],
    'Email' => [CustomFieldType::Email, 'Email'],
    'Website' => [CustomFieldType::Website, 'Website'],
    'ListOfValues' => [CustomFieldType::ListOfValues, 'List of Values'],
    'MultiListOfValues' => [CustomFieldType::MultiListOfValues, 'Multi List of Values'],
    'AutoNumber' => [CustomFieldType::AutoNumber, 'Auto Number'],
    'Currency' => [CustomFieldType::Currency, 'Currency'],
    'Telephone' => [CustomFieldType::Telephone, 'Telephone'],
    'FileImage' => [CustomFieldType::FileImage, 'File / Image'],
    'RichText' => [CustomFieldType::RichText, 'Rich Text'],
    'JsonKeyValue' => [CustomFieldType::JsonKeyValue, 'JSON Key-Value'],
    'Colour' => [CustomFieldType::Colour, 'Colour'],
    'Percentage' => [CustomFieldType::Percentage, 'Percentage'],
]);

it('has a value column for every case', function () {
    foreach (CustomFieldType::cases() as $type) {
        expect($type->valueColumn())->toBeString()->toStartWith('value_');
    }
});

it('maps field types to correct value columns', function (CustomFieldType $type, string $expected) {
    expect($type->valueColumn())->toBe($expected);
})->with([
    'String → string' => [CustomFieldType::String, 'value_string'],
    'Text → text' => [CustomFieldType::Text, 'value_text'],
    'Number → decimal' => [CustomFieldType::Number, 'value_decimal'],
    'Boolean → boolean' => [CustomFieldType::Boolean, 'value_boolean'],
    'DateTime → datetime' => [CustomFieldType::DateTime, 'value_datetime'],
    'Date → date' => [CustomFieldType::Date, 'value_date'],
    'Time → time' => [CustomFieldType::Time, 'value_time'],
    'Email → string' => [CustomFieldType::Email, 'value_string'],
    'Website → string' => [CustomFieldType::Website, 'value_string'],
    'ListOfValues → integer' => [CustomFieldType::ListOfValues, 'value_integer'],
    'MultiListOfValues → json' => [CustomFieldType::MultiListOfValues, 'value_json'],
    'AutoNumber → string' => [CustomFieldType::AutoNumber, 'value_string'],
    'Currency → decimal' => [CustomFieldType::Currency, 'value_decimal'],
    'Telephone → string' => [CustomFieldType::Telephone, 'value_string'],
    'FileImage → json' => [CustomFieldType::FileImage, 'value_json'],
    'RichText → text' => [CustomFieldType::RichText, 'value_text'],
    'JsonKeyValue → json' => [CustomFieldType::JsonKeyValue, 'value_json'],
    'Colour → string' => [CustomFieldType::Colour, 'value_string'],
    'Percentage → decimal' => [CustomFieldType::Percentage, 'value_decimal'],
]);
