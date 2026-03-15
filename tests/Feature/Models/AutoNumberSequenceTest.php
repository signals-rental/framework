<?php

use App\Models\AutoNumberSequence;
use App\Models\CustomField;

it('creates an auto number sequence', function () {
    $sequence = AutoNumberSequence::factory()->create();

    expect($sequence)->toBeInstanceOf(AutoNumberSequence::class)
        ->and($sequence->next_value)->toBe(1)
        ->and($sequence->prefix)->toBeNull()
        ->and($sequence->suffix)->toBeNull();
});

it('creates a sequence with prefix and suffix', function () {
    $sequence = AutoNumberSequence::factory()
        ->withPrefix('INV-')
        ->withSuffix('-A')
        ->create(['next_value' => 100]);

    expect($sequence->prefix)->toBe('INV-')
        ->and($sequence->suffix)->toBe('-A')
        ->and($sequence->next_value)->toBe(100);
});

it('belongs to a custom field', function () {
    $field = CustomField::factory()->autoNumber()->create();
    $sequence = AutoNumberSequence::factory()->create([
        'custom_field_id' => $field->id,
    ]);

    expect($sequence->customField->id)->toBe($field->id);
});

it('casts next_value as integer', function () {
    $sequence = AutoNumberSequence::factory()->create(['next_value' => 42]);

    $sequence->refresh();

    expect($sequence->next_value)->toBe(42)
        ->and($sequence->next_value)->toBeInt();
});

it('enforces unique custom_field_id', function () {
    $field = CustomField::factory()->autoNumber()->create();
    AutoNumberSequence::factory()->create(['custom_field_id' => $field->id]);

    expect(fn () => AutoNumberSequence::factory()->create(['custom_field_id' => $field->id]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('cascades delete from custom field', function () {
    $field = CustomField::factory()->autoNumber()->create();
    AutoNumberSequence::factory()->create(['custom_field_id' => $field->id]);

    expect(AutoNumberSequence::query()->where('custom_field_id', $field->id)->count())->toBe(1);

    $field->delete();

    expect(AutoNumberSequence::query()->where('custom_field_id', $field->id)->count())->toBe(0);
});
