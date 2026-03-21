<?php

use App\Models\AutoNumberSequence;
use App\Models\CustomField;
use App\Services\AutoNumberService;

describe('AutoNumberService', function () {
    it('generates formatted auto-number with prefix and suffix', function () {
        $field = CustomField::factory()->forModule('Member')->create([
            'name' => 'invoice_number',
            'field_type' => \App\Enums\CustomFieldType::AutoNumber,
        ]);

        AutoNumberSequence::create([
            'custom_field_id' => $field->id,
            'prefix' => 'INV-',
            'suffix' => '',
            'next_value' => 1,
        ]);

        $service = new AutoNumberService;
        $number = $service->generate($field->id);

        expect($number)->toBe('INV-00001');
    });

    it('increments atomically on each call', function () {
        $field = CustomField::factory()->forModule('Member')->create([
            'name' => 'order_number',
            'field_type' => \App\Enums\CustomFieldType::AutoNumber,
        ]);

        AutoNumberSequence::create([
            'custom_field_id' => $field->id,
            'prefix' => 'ORD-',
            'suffix' => '',
            'next_value' => 100,
        ]);

        $service = new AutoNumberService;
        $first = $service->generate($field->id);
        $second = $service->generate($field->id);

        expect($first)->toBe('ORD-00100');
        expect($second)->toBe('ORD-00101');
    });

    it('generates with suffix', function () {
        $field = CustomField::factory()->forModule('Store')->create([
            'name' => 'asset_tag',
            'field_type' => \App\Enums\CustomFieldType::AutoNumber,
        ]);

        AutoNumberSequence::create([
            'custom_field_id' => $field->id,
            'prefix' => 'AST',
            'suffix' => '-UK',
            'next_value' => 42,
        ]);

        $service = new AutoNumberService;
        $number = $service->generate($field->id);

        expect($number)->toBe('AST00042-UK');
    });

    it('previews next number without consuming', function () {
        $field = CustomField::factory()->forModule('Member')->create([
            'name' => 'ref_number',
            'field_type' => \App\Enums\CustomFieldType::AutoNumber,
        ]);

        AutoNumberSequence::create([
            'custom_field_id' => $field->id,
            'prefix' => 'REF-',
            'suffix' => '',
            'next_value' => 50,
        ]);

        $service = new AutoNumberService;
        $preview = $service->preview($field->id);

        expect($preview)->toBe('REF-00050');

        // Value should not have changed
        $sequence = AutoNumberSequence::where('custom_field_id', $field->id)->first();
        expect($sequence->next_value)->toBe(50);
    });

    it('resets sequence to a specific value', function () {
        $field = CustomField::factory()->forModule('Member')->create([
            'name' => 'reset_test',
            'field_type' => \App\Enums\CustomFieldType::AutoNumber,
        ]);

        AutoNumberSequence::create([
            'custom_field_id' => $field->id,
            'prefix' => '',
            'suffix' => '',
            'next_value' => 999,
        ]);

        $service = new AutoNumberService;
        $service->reset($field->id, 1);

        $sequence = AutoNumberSequence::where('custom_field_id', $field->id)->first();
        expect($sequence->next_value)->toBe(1);
    });

    it('throws when sequence does not exist for generate', function () {
        $service = new AutoNumberService;
        $service->generate(99999);
    })->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

    it('throws when sequence does not exist for preview', function () {
        $service = new AutoNumberService;
        $service->preview(99999);
    })->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

    it('respects custom pad width', function () {
        $field = CustomField::factory()->forModule('Member')->create([
            'name' => 'short_number',
            'field_type' => \App\Enums\CustomFieldType::AutoNumber,
        ]);

        AutoNumberSequence::create([
            'custom_field_id' => $field->id,
            'prefix' => '#',
            'suffix' => '',
            'next_value' => 7,
        ]);

        $service = new AutoNumberService;
        $number = $service->generate($field->id, 3);

        expect($number)->toBe('#007');
    });
});
