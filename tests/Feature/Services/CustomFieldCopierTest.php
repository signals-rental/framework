<?php

use App\Enums\CustomFieldType;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\ListName;
use App\Models\ListValue;
use App\Models\Member;
use App\Models\Store;
use App\Services\CustomFieldCopier;
use App\Services\CustomFieldValidator;

describe('CustomFieldCopier', function () {
    beforeEach(function () {
        $this->copier = new CustomFieldCopier(new CustomFieldValidator);

        $this->sourceMember = Member::factory()->create();
        $this->targetStore = Store::factory()->create();
    });

    it('copies matching fields between modules', function () {
        $sourceField = CustomField::factory()->create([
            'name' => 'po_reference',
            'module_type' => 'Member',
            'field_type' => CustomFieldType::String,
        ]);

        $targetField = CustomField::factory()->create([
            'name' => 'po_reference',
            'module_type' => 'Store',
            'field_type' => CustomFieldType::String,
        ]);

        CustomFieldValue::factory()->create([
            'custom_field_id' => $sourceField->id,
            'entity_type' => Member::class,
            'entity_id' => $this->sourceMember->id,
            'value_string' => 'PO-123',
        ]);

        $result = $this->copier->copy($this->sourceMember, $this->targetStore, 'Store');

        expect($result->copied)->toBe(1)
            ->and($result->fieldsCopied)->toBe(['po_reference']);

        $targetValue = CustomFieldValue::query()
            ->where('custom_field_id', $targetField->id)
            ->where('entity_type', Store::class)
            ->where('entity_id', $this->targetStore->id)
            ->first();

        expect($targetValue)->not->toBeNull()
            ->and($targetValue->value_string)->toBe('PO-123');
    });

    it('skips fields only on source module', function () {
        $sourceField = CustomField::factory()->create([
            'name' => 'source_only_field',
            'module_type' => 'Member',
            'field_type' => CustomFieldType::String,
        ]);

        CustomFieldValue::factory()->create([
            'custom_field_id' => $sourceField->id,
            'entity_type' => Member::class,
            'entity_id' => $this->sourceMember->id,
            'value_string' => 'some value',
        ]);

        $result = $this->copier->copy($this->sourceMember, $this->targetStore, 'Store');

        expect($result->copied)->toBe(0)
            ->and($result->skipped)->toBe(1)
            ->and($result->fieldsSkipped)->toBe(['source_only_field']);

        expect(CustomFieldValue::query()
            ->where('entity_type', Store::class)
            ->where('entity_id', $this->targetStore->id)
            ->count()
        )->toBe(0);
    });

    it('skips fields with same name but different field type', function () {
        $sourceField = CustomField::factory()->create([
            'name' => 'notes',
            'module_type' => 'Member',
            'field_type' => CustomFieldType::String,
        ]);

        CustomField::factory()->create([
            'name' => 'notes',
            'module_type' => 'Store',
            'field_type' => CustomFieldType::Number,
        ]);

        CustomFieldValue::factory()->create([
            'custom_field_id' => $sourceField->id,
            'entity_type' => Member::class,
            'entity_id' => $this->sourceMember->id,
            'value_string' => 'some notes',
        ]);

        $result = $this->copier->copy($this->sourceMember, $this->targetStore, 'Store');

        expect($result->copied)->toBe(0)
            ->and($result->skipped)->toBe(1)
            ->and($result->fieldsSkipped)->toBe(['notes']);
    });

    it('skips fields failing target validation', function () {
        CustomField::factory()->create([
            'name' => 'strict_field',
            'module_type' => 'Member',
            'field_type' => CustomFieldType::String,
        ]);

        CustomField::factory()->create([
            'name' => 'strict_field',
            'module_type' => 'Store',
            'field_type' => CustomFieldType::String,
            'is_required' => true,
            'validation_rules' => ['min_length' => 10],
        ]);

        $sourceField = CustomField::query()
            ->where('name', 'strict_field')
            ->where('module_type', 'Member')
            ->first();

        CustomFieldValue::factory()->create([
            'custom_field_id' => $sourceField->id,
            'entity_type' => Member::class,
            'entity_id' => $this->sourceMember->id,
            'value_string' => 'short',
        ]);

        $result = $this->copier->copy($this->sourceMember, $this->targetStore, 'Store');

        expect($result->copied)->toBe(0)
            ->and($result->skipped)->toBe(1)
            ->and($result->fieldsSkipped)->toBe(['strict_field']);
    });

    it('copies ListOfValues field values', function () {
        $listName = ListName::factory()->create();
        $listValue = ListValue::factory()->forList($listName)->create(['name' => 'Option A']);

        $sourceField = CustomField::factory()->create([
            'name' => 'category',
            'module_type' => 'Member',
            'field_type' => CustomFieldType::ListOfValues,
            'list_name_id' => $listName->id,
        ]);

        $targetField = CustomField::factory()->create([
            'name' => 'category',
            'module_type' => 'Store',
            'field_type' => CustomFieldType::ListOfValues,
            'list_name_id' => $listName->id,
        ]);

        CustomFieldValue::factory()->create([
            'custom_field_id' => $sourceField->id,
            'entity_type' => Member::class,
            'entity_id' => $this->sourceMember->id,
            'value_string' => null,
            'value_integer' => $listValue->id,
        ]);

        $result = $this->copier->copy($this->sourceMember, $this->targetStore, 'Store');

        expect($result->copied)->toBe(1);

        $targetValue = CustomFieldValue::query()
            ->where('custom_field_id', $targetField->id)
            ->where('entity_type', Store::class)
            ->where('entity_id', $this->targetStore->id)
            ->first();

        expect($targetValue->value_integer)->toBe($listValue->id);
    });

    it('handles empty source with no custom field values', function () {
        $result = $this->copier->copy($this->sourceMember, $this->targetStore, 'Store');

        expect($result->copied)->toBe(0)
            ->and($result->skipped)->toBe(0)
            ->and($result->fieldsCopied)->toBe([])
            ->and($result->fieldsSkipped)->toBe([]);
    });

    it('returns accurate CopyResult counts and arrays', function () {
        $sourceMatch = CustomField::factory()->create([
            'name' => 'region',
            'module_type' => 'Member',
            'field_type' => CustomFieldType::String,
        ]);
        CustomField::factory()->create([
            'name' => 'region',
            'module_type' => 'Store',
            'field_type' => CustomFieldType::String,
        ]);

        $sourceOnly = CustomField::factory()->number()->create([
            'name' => 'member_score',
            'module_type' => 'Member',
        ]);

        CustomFieldValue::factory()->create([
            'custom_field_id' => $sourceMatch->id,
            'entity_type' => Member::class,
            'entity_id' => $this->sourceMember->id,
            'value_string' => 'North',
        ]);

        CustomFieldValue::factory()->create([
            'custom_field_id' => $sourceOnly->id,
            'entity_type' => Member::class,
            'entity_id' => $this->sourceMember->id,
            'value_string' => null,
            'value_decimal' => 42.0,
        ]);

        $result = $this->copier->copy($this->sourceMember, $this->targetStore, 'Store');

        expect($result->copied)->toBe(1)
            ->and($result->skipped)->toBe(1)
            ->and($result->fieldsCopied)->toBe(['region'])
            ->and($result->fieldsSkipped)->toBe(['member_score']);
    });

    it('does not modify source values after copy', function () {
        $sourceField = CustomField::factory()->create([
            'name' => 'ref_code',
            'module_type' => 'Member',
            'field_type' => CustomFieldType::String,
        ]);

        CustomField::factory()->create([
            'name' => 'ref_code',
            'module_type' => 'Store',
            'field_type' => CustomFieldType::String,
        ]);

        CustomFieldValue::factory()->create([
            'custom_field_id' => $sourceField->id,
            'entity_type' => Member::class,
            'entity_id' => $this->sourceMember->id,
            'value_string' => 'REF-001',
        ]);

        $this->copier->copy($this->sourceMember, $this->targetStore, 'Store');

        $sourceValue = CustomFieldValue::query()
            ->where('custom_field_id', $sourceField->id)
            ->where('entity_type', Member::class)
            ->where('entity_id', $this->sourceMember->id)
            ->first();

        expect($sourceValue->value_string)->toBe('REF-001');
    });

    it('copies multiple field types in one operation', function () {
        $sourceString = CustomField::factory()->create([
            'name' => 'label',
            'module_type' => 'Member',
            'field_type' => CustomFieldType::String,
        ]);
        CustomField::factory()->create([
            'name' => 'label',
            'module_type' => 'Store',
            'field_type' => CustomFieldType::String,
        ]);

        $sourceNum = CustomField::factory()->number()->create([
            'name' => 'priority',
            'module_type' => 'Member',
        ]);
        CustomField::factory()->number()->create([
            'name' => 'priority',
            'module_type' => 'Store',
        ]);

        $sourceBool = CustomField::factory()->boolean()->create([
            'name' => 'is_vip',
            'module_type' => 'Member',
        ]);
        CustomField::factory()->boolean()->create([
            'name' => 'is_vip',
            'module_type' => 'Store',
        ]);

        CustomFieldValue::factory()->create([
            'custom_field_id' => $sourceString->id,
            'entity_type' => Member::class,
            'entity_id' => $this->sourceMember->id,
            'value_string' => 'Premium',
        ]);

        CustomFieldValue::factory()->create([
            'custom_field_id' => $sourceNum->id,
            'entity_type' => Member::class,
            'entity_id' => $this->sourceMember->id,
            'value_string' => null,
            'value_decimal' => 5.0,
        ]);

        CustomFieldValue::factory()->create([
            'custom_field_id' => $sourceBool->id,
            'entity_type' => Member::class,
            'entity_id' => $this->sourceMember->id,
            'value_string' => null,
            'value_boolean' => true,
        ]);

        $result = $this->copier->copy($this->sourceMember, $this->targetStore, 'Store');

        expect($result->copied)->toBe(3)
            ->and($result->skipped)->toBe(0)
            ->and($result->fieldsCopied)->toContain('label', 'priority', 'is_vip');
    });

    it('updates existing target values on copy', function () {
        $sourceField = CustomField::factory()->create([
            'name' => 'tag',
            'module_type' => 'Member',
            'field_type' => CustomFieldType::String,
        ]);

        $targetField = CustomField::factory()->create([
            'name' => 'tag',
            'module_type' => 'Store',
            'field_type' => CustomFieldType::String,
        ]);

        CustomFieldValue::factory()->create([
            'custom_field_id' => $targetField->id,
            'entity_type' => Store::class,
            'entity_id' => $this->targetStore->id,
            'value_string' => 'old-value',
        ]);

        CustomFieldValue::factory()->create([
            'custom_field_id' => $sourceField->id,
            'entity_type' => Member::class,
            'entity_id' => $this->sourceMember->id,
            'value_string' => 'new-value',
        ]);

        $result = $this->copier->copy($this->sourceMember, $this->targetStore, 'Store');

        expect($result->copied)->toBe(1);

        $targetValue = CustomFieldValue::query()
            ->where('custom_field_id', $targetField->id)
            ->where('entity_type', Store::class)
            ->where('entity_id', $this->targetStore->id)
            ->first();

        expect($targetValue->value_string)->toBe('new-value');

        expect(CustomFieldValue::query()
            ->where('custom_field_id', $targetField->id)
            ->where('entity_type', Store::class)
            ->where('entity_id', $this->targetStore->id)
            ->count()
        )->toBe(1);
    });

    it('skips null source values', function () {
        $sourceField = CustomField::factory()->create([
            'name' => 'empty_field',
            'module_type' => 'Member',
            'field_type' => CustomFieldType::String,
        ]);

        CustomField::factory()->create([
            'name' => 'empty_field',
            'module_type' => 'Store',
            'field_type' => CustomFieldType::String,
        ]);

        CustomFieldValue::factory()->create([
            'custom_field_id' => $sourceField->id,
            'entity_type' => Member::class,
            'entity_id' => $this->sourceMember->id,
            'value_string' => null,
        ]);

        $result = $this->copier->copy($this->sourceMember, $this->targetStore, 'Store');

        expect($result->copied)->toBe(0)
            ->and($result->skipped)->toBe(1)
            ->and($result->fieldsSkipped)->toBe(['empty_field']);
    });
});

describe('CopyResult', function () {
    it('serializes to array correctly', function () {
        $result = new \App\ValueObjects\CopyResult(
            copied: 2,
            skipped: 1,
            fieldsCopied: ['region', 'priority'],
            fieldsSkipped: ['notes'],
        );

        expect($result->toArray())->toBe([
            'copied' => 2,
            'skipped' => 1,
            'fields_copied' => ['region', 'priority'],
            'fields_skipped' => ['notes'],
        ]);
    });

    it('has empty arrays by default', function () {
        $result = new \App\ValueObjects\CopyResult(copied: 0, skipped: 0);

        expect($result->fieldsCopied)->toBe([])
            ->and($result->fieldsSkipped)->toBe([])
            ->and($result->toArray())->toBe([
                'copied' => 0,
                'skipped' => 0,
                'fields_copied' => [],
                'fields_skipped' => [],
            ]);
    });
});
