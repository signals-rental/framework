<?php

use App\Enums\CustomFieldType;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Member;
use App\Models\Store;
use App\Services\CustomFieldCopier;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// Lives in Unit (not Feature) to opt out of the global RefreshDatabase
// transaction: SQLite ignores `PRAGMA foreign_keys` toggles inside a
// transaction. With DatabaseMigrations there is no wrapping transaction, so we
// can disable foreign keys, delete the source field without cascading, and
// leave an orphaned value whose customField relation resolves to null.
uses(TestCase::class, DatabaseMigrations::class);

it('silently skips source values whose custom field row no longer exists', function () {
    $sourceMember = Member::factory()->create();
    $targetStore = Store::factory()->create();

    $sourceField = CustomField::factory()->create([
        'name' => 'orphaned_field',
        'module_type' => 'Member',
        'field_type' => CustomFieldType::String,
    ]);

    CustomFieldValue::factory()->create([
        'custom_field_id' => $sourceField->id,
        'entity_type' => Member::class,
        'entity_id' => $sourceMember->id,
        'value_string' => 'some value',
    ]);

    // Orphan the value: delete the field without the FK cascade removing it.
    Schema::disableForeignKeyConstraints();
    $sourceField->delete();
    Schema::enableForeignKeyConstraints();

    // Sanity check: the value survived, so its field relation is now null.
    expect(CustomFieldValue::query()->where('custom_field_id', $sourceField->id)->exists())->toBeTrue();

    $result = app(CustomFieldCopier::class)->copy($sourceMember, $targetStore, 'Store');

    expect($result->copied)->toBe(0)
        ->and($result->skipped)->toBe(0);
});
