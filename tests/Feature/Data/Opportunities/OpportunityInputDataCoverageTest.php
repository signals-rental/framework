<?php

use App\Data\Opportunities\AddOpportunityCostData;
use App\Data\Opportunities\ChangeVersionLabelData;
use App\Data\Opportunities\MergeOpportunityItemsData;
use App\Data\Opportunities\OpportunityData;
use App\Data\Opportunities\OpportunityItemAssetData;
use App\Data\Opportunities\UpdateOpportunityCostData;
use App\Data\Opportunities\UpdateOpportunityData;
use App\Data\Opportunities\UpdateOpportunityItemDetailsData;
use App\Enums\AssetAssignmentStatus;
use App\Enums\OpportunityCostType;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

describe('AddOpportunityCostData', function () {
    it('enforces the rules() constraints', function () {
        expect(fn () => AddOpportunityCostData::validateAndCreate(['description' => '']))
            ->toThrow(ValidationException::class);

        $data = AddOpportunityCostData::validateAndCreate([
            'description' => 'Delivery surcharge',
            'cost_type' => OpportunityCostType::Delivery->value,
            'quantity' => '2',
        ]);

        expect($data->description)->toBe('Delivery surcharge')
            ->and($data->cost_type)->toBe(OpportunityCostType::Delivery->value);
    });

    it('exposes friendly enum messages() and uses them on invalid enum input', function () {
        expect(AddOpportunityCostData::messages())
            ->toBe([
                'cost_type.enum' => 'The selected cost type is invalid.',
                'transaction_type.enum' => 'The selected transaction type is invalid.',
            ]);

        try {
            AddOpportunityCostData::validateAndCreate([
                'description' => 'Bad cost',
                'cost_type' => 99999,
            ]);
            $this->fail('Expected a ValidationException for an invalid cost_type.');
        } catch (ValidationException $e) {
            expect($e->errors()['cost_type'][0])->toBe('The selected cost type is invalid.');
        }
    });
});

describe('UpdateOpportunityCostData', function () {
    it('validates optional fields and skips omitted ones', function () {
        $data = UpdateOpportunityCostData::validateAndCreate(['description' => 'Updated cost']);

        expect($data->description)->toBe('Updated cost');
    });

    it('exposes friendly enum messages() and uses them on invalid enum input', function () {
        expect(UpdateOpportunityCostData::messages())
            ->toBe([
                'cost_type.enum' => 'The selected cost type is invalid.',
                'transaction_type.enum' => 'The selected transaction type is invalid.',
            ]);

        try {
            UpdateOpportunityCostData::validateAndCreate(['transaction_type' => 88888]);
            $this->fail('Expected a ValidationException for an invalid transaction_type.');
        } catch (ValidationException $e) {
            expect($e->errors()['transaction_type'][0])->toBe('The selected transaction type is invalid.');
        }
    });
});

describe('ChangeVersionLabelData', function () {
    it('requires the label key to be present but allows null', function () {
        // Missing key fails the `present` rule.
        expect(fn () => ChangeVersionLabelData::validateAndCreate([]))
            ->toThrow(ValidationException::class);

        $nulled = ChangeVersionLabelData::validateAndCreate(['label' => null]);
        expect($nulled->label)->toBeNull();

        $named = ChangeVersionLabelData::validateAndCreate(['label' => 'Final quote']);
        expect($named->label)->toBe('Final quote');
    });

    it('rejects an over-length label', function () {
        expect(fn () => ChangeVersionLabelData::validateAndCreate(['label' => str_repeat('x', 256)]))
            ->toThrow(ValidationException::class);
    });
});

describe('MergeOpportunityItemsData', function () {
    it('requires a non-empty array of existing item ids', function () {
        expect(fn () => MergeOpportunityItemsData::validateAndCreate(['duplicate_item_ids' => []]))
            ->toThrow(ValidationException::class);

        $opportunity = Opportunity::factory()->create();
        $item = OpportunityItem::factory()->for($opportunity)->create();

        $data = MergeOpportunityItemsData::validateAndCreate(['duplicate_item_ids' => [$item->id]]);
        expect($data->duplicate_item_ids)->toBe([$item->id]);
    });

    it('rejects a duplicate id that does not exist', function () {
        expect(fn () => MergeOpportunityItemsData::validateAndCreate(['duplicate_item_ids' => [999999]]))
            ->toThrow(ValidationException::class);
    });
});

describe('UpdateOpportunityItemDetailsData', function () {
    it('validates optional nullable description and notes', function () {
        $cleared = UpdateOpportunityItemDetailsData::validateAndCreate([
            'description' => null,
            'notes' => null,
        ]);
        expect($cleared->description)->toBeNull()
            ->and($cleared->notes)->toBeNull();

        expect(fn () => UpdateOpportunityItemDetailsData::validateAndCreate(['description' => ['not-a-string']]))
            ->toThrow(ValidationException::class);
    });
});

describe('UpdateOpportunityData address rule', function () {
    it('constrains the address rule to the supplied member_id in payload context', function () {
        // Build a payload that supplies member_id so the rules() branch that further
        // constrains addressable_id to that member is exercised. We pass a
        // non-existent address id so the constrained exists rule fails.
        $member = Member::factory()->organisation()->create();

        $rules = UpdateOpportunityData::getValidationRules([
            'member_id' => $member->id,
            'delivery_address_id' => 987654,
        ]);

        // The delivery_address_id rule should now include an Exists rule scoped to
        // addressable_id = member_id; validating a bogus id must fail.
        $validator = Validator::make(
            ['member_id' => $member->id, 'delivery_address_id' => 987654],
            $rules,
        );

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('delivery_address_id'))->toBeTrue();
    });
});

describe('OpportunityData::reference null guard', function () {
    it('returns null for a loaded but absent store relation', function () {
        $opportunity = Opportunity::factory()->create(['store_id' => null]);

        $output = OpportunityData::fromModel($opportunity->fresh()->load('store'))
            ->include('store')
            ->toArray();

        expect($output['store'])->toBeNull();
    });
});

describe('OpportunityItemAssetData::reference null guard', function () {
    it('returns null for a loaded but absent stock level relation', function () {
        $asset = OpportunityItemAsset::factory()->create([
            'stock_level_id' => null,
            'status' => AssetAssignmentStatus::Allocated->value,
        ]);

        $output = OpportunityItemAssetData::fromModel($asset->fresh()->load('stockLevel'))
            ->include('stock_level')
            ->toArray();

        expect($output['stock_level'])->toBeNull();
    });
});
