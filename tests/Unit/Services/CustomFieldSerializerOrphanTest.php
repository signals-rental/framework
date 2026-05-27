<?php

use App\Enums\CustomFieldType;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Member;
use App\Services\CustomFieldSerializer;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// Uses DatabaseMigrations (not the global RefreshDatabase transaction) so SQLite
// honours the foreign-key toggle: we delete a custom field without cascading,
// leaving an orphaned value whose customField relation resolves to null.
uses(TestCase::class, DatabaseMigrations::class);

it('skips orphaned custom field values when serialising to array', function () {
    $member = Member::factory()->create();

    $field = CustomField::factory()->create([
        'name' => 'orphaned_field',
        'module_type' => 'Member',
        'field_type' => CustomFieldType::String,
    ]);

    CustomFieldValue::factory()->create([
        'custom_field_id' => $field->id,
        'entity_type' => $member->getMorphClass(),
        'entity_id' => $member->id,
        'value_string' => 'some value',
    ]);

    // Orphan the value: delete the field without the FK cascade removing it.
    Schema::disableForeignKeyConstraints();
    $field->delete();
    Schema::enableForeignKeyConstraints();

    expect(CustomFieldValue::query()->where('custom_field_id', $field->id)->exists())->toBeTrue();

    $result = app(CustomFieldSerializer::class)->toArray($member->fresh());

    // The orphaned value is silently skipped; the deleted field is absent.
    expect($result)->toBeArray()
        ->and($result)->not->toHaveKey('orphaned_field');
});
