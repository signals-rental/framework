<?php

use App\Actions\Opportunities\DiffVersions;
use App\Data\Opportunities\OpportunityCostData;
use App\Data\Opportunities\OpportunityData;
use App\Data\Opportunities\OpportunityItemAssetData;
use App\Data\Opportunities\OpportunityVersionData;
use App\Data\Opportunities\ParticipantData;
use App\Data\Opportunities\ProductSearchAccessoryData;
use App\Data\Opportunities\VersionDiffData;
use App\Data\Opportunities\VersionDiffItemData;
use App\Enums\AssetAssignmentStatus;
use App\Enums\OpportunityItemType;
use App\Enums\OpportunityState;
use App\Enums\OpportunityStatus;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityCost;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use App\Models\OpportunityParticipant;
use App\Models\OpportunityVersion;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

it('maps OpportunityData from a model with decimal-string money and state labels', function () {
    $member = Member::factory()->organisation()->create(['name' => 'Acme Hire']);
    $opportunity = Opportunity::factory()->quotation()->create([
        'member_id' => $member->id,
        'subject' => 'Summer festival',
        'charge_total' => 12550,
        'tax_total' => 2510,
        'currency_code' => 'GBP',
        'exchange_rate' => '1.000000',
        'tag_list' => ['vip', 'rush'],
    ]);

    $output = OpportunityData::fromModel($opportunity->fresh(['member']))
        ->include('member')
        ->toArray();

    expect($output['subject'])->toBe('Summer festival')
        ->and($output['state_label'])->toBe('Quotation')
        ->and($output['status_label'])->toBe(OpportunityStatus::QuotationProvisional->label())
        ->and($output['charge_total'])->toBe('125.50')
        ->and($output['tax_total'])->toBe('25.10')
        ->and($output['currency_code'])->toBe('GBP')
        ->and($output['tag_list'])->toBe(['vip', 'rush'])
        ->and($output['member']['name'])->toBe('Acme Hire');
});

it('resolves the default status for a state via OpportunityData::defaultStatusFor', function () {
    expect(OpportunityData::defaultStatusFor(OpportunityState::Draft->value))
        ->toBe(OpportunityStatus::DraftOpen->statusValue())
        ->and(OpportunityData::defaultStatusFor(OpportunityState::Order->value))
        ->toBe(OpportunityStatus::OrderActive->statusValue());
});

it('includes lazy nested collections when eager-loaded on OpportunityData', function () {
    $opportunity = Opportunity::factory()->create();
    $version = OpportunityVersion::factory()->for($opportunity)->create(['version_number' => 1]);
    $opportunity->forceFill(['active_version_id' => $version->id])->saveQuietly();
    OpportunityItem::factory()->for($opportunity)->create(['version_id' => $version->id, 'name' => 'Line A']);
    $cost = OpportunityCost::factory()->for($opportunity)->create(['description' => 'Delivery', 'amount' => 5000]);
    $participant = OpportunityParticipant::factory()->for($opportunity)->create(['role' => 'Site contact']);

    $data = OpportunityData::fromModel(
        $opportunity->fresh()->load(['items', 'costs', 'versions', 'participants.member']),
    )->include('items', 'costs', 'versions', 'participants');

    $array = $data->toArray();

    expect($array['items'])->toHaveCount(1)
        ->and($array['items'][0]['name'])->toBe('Line A')
        ->and($array['costs'][0]['description'])->toBe('Delivery')
        ->and($array['versions'][0]['version_number'])->toBe(1)
        ->and($array['participants'][0]['role'])->toBe('Site contact');
});

it('maps OpportunityCostData with enum labels and decimal-string amount', function () {
    $cost = OpportunityCost::factory()->delivery()->create([
        'amount' => 9999,
        'quantity' => 2,
    ]);

    $output = OpportunityCostData::fromModel($cost->fresh())->toArray();

    expect($output['description'])->toBe('Delivery')
        ->and($output['cost_type_label'])->toBe('Delivery')
        ->and($output['transaction_type_label'])->toBe('Service')
        ->and($output['amount'])->toBe('99.99')
        ->and($output['quantity'])->toBe('2.00');
});

it('maps OpportunityItemAssetData with status labels and lazy stock level reference', function () {
    $stockLevel = StockLevel::factory()->serialised()->create(['asset_number' => 'A-500']);
    $asset = OpportunityItemAsset::factory()->create([
        'stock_level_id' => $stockLevel->id,
        'status' => AssetAssignmentStatus::Dispatched->value,
    ]);

    $bare = OpportunityItemAssetData::fromModel($asset->fresh())->toArray();
    $loaded = OpportunityItemAssetData::fromModel($asset->fresh()->load('stockLevel'))
        ->include('stock_level')
        ->toArray();

    expect($bare['status_label'])->toBe(AssetAssignmentStatus::Dispatched->label())
        ->and($bare)->not->toHaveKey('stock_level')
        ->and($loaded['stock_level']['name'])->toBe($stockLevel->fresh()->item_name ?? $stockLevel->asset_number);
});

it('maps OpportunityVersionData with money totals and lazy item rows', function () {
    $opportunity = Opportunity::factory()->create();
    $version = OpportunityVersion::factory()->for($opportunity)->create([
        'version_number' => 2,
        'charge_total' => 20000,
        'tax_total' => 4000,
        'charge_excluding_tax_total' => 16000,
        'charge_including_tax_total' => 20000,
        'label' => 'Revised quote',
    ]);
    OpportunityItem::factory()->for($opportunity)->create([
        'version_id' => $version->id,
        'name' => 'Version line',
    ]);

    $output = OpportunityVersionData::fromModel($version->fresh()->load('items'))
        ->include('items')
        ->toArray();

    expect($output['version_number'])->toBe(2)
        ->and($output['label'])->toBe('Revised quote')
        ->and($output['charge_total'])->toBe('200.00')
        ->and($output['tax_total'])->toBe('40.00')
        ->and($output['items'][0]['name'])->toBe('Version line');
});

it('maps ParticipantData with lazy member reference', function () {
    $member = Member::factory()->contact()->create(['name' => 'Pat Lee']);
    $participant = OpportunityParticipant::factory()->create([
        'member_id' => $member->id,
        'role' => 'Primary contact',
        'mute' => true,
    ]);

    $output = ParticipantData::fromModel($participant->fresh()->load('member'))
        ->include('member')
        ->toArray();

    expect($output['role'])->toBe('Primary contact')
        ->and($output['mute'])->toBeTrue()
        ->and($output['member']['name'])->toBe('Pat Lee');
});

it('constructs ProductSearchAccessoryData with ratio and flags', function () {
    $accessory = new ProductSearchAccessoryData(
        id: 10,
        name: 'Safety Clamp',
        sku: 'CLMP-1',
        ratio: '2.00',
        included: true,
        zero_priced: false,
    );

    expect($accessory->ratio)->toBe('2.00')
        ->and($accessory->included)->toBeTrue()
        ->and($accessory->zero_priced)->toBeFalse();
});

it('constructs VersionDiffData and VersionDiffItemData shapes for diff consumers', function () {
    $item = new VersionDiffItemData(
        item_id: 5,
        item_type: 'product',
        name: 'PA Speaker',
        source_quantity: '2.00',
        target_quantity: '4.00',
        source_unit_price: '50.00',
        target_unit_price: '50.00',
        source_discount_percent: null,
        target_discount_percent: null,
        source_total: '100.00',
        target_total: '200.00',
        total_delta: '100.00',
    );

    $diff = new VersionDiffData(
        source_version_id: 1,
        target_version_id: 2,
        source_version_number: 1,
        target_version_number: 2,
        added: [$item],
        removed: [],
        changed: [],
        source_total: '100.00',
        target_total: '200.00',
        net_change: '100.00',
    );

    expect($diff)->toBeInstanceOf(VersionDiffData::class)
        ->and($diff->added[0]->name)->toBe('PA Speaker')
        ->and($diff->net_change)->toBe('100.00');
});

describe('DiffVersions DTO line shapes', function () {
    beforeEach(function () {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->actingAs(User::factory()->owner()->create());
    });

    it('builds VersionDiffItemData lines through DiffVersions for added and removed rows', function () {
        $opportunity = Opportunity::factory()->create();
        $productKept = Product::factory()->create(['name' => 'Kept']);
        $productRemoved = Product::factory()->create(['name' => 'Removed']);

        $source = OpportunityVersion::factory()->for($opportunity)->create(['version_number' => 1]);
        $target = OpportunityVersion::factory()->for($opportunity)->create(['version_number' => 2]);

        OpportunityItem::factory()->for($opportunity)->create([
            'version_id' => $source->id,
            'itemable_id' => $productKept->id,
            'itemable_type' => Product::class,
            'item_type' => OpportunityItemType::Product->value,
            'name' => 'Kept',
            'quantity' => 1,
            'unit_price' => 1000,
            'total' => 1000,
        ]);
        OpportunityItem::factory()->for($opportunity)->create([
            'version_id' => $source->id,
            'itemable_id' => $productRemoved->id,
            'itemable_type' => Product::class,
            'item_type' => OpportunityItemType::Product->value,
            'name' => 'Removed',
            'quantity' => 1,
            'unit_price' => 2000,
            'total' => 2000,
        ]);
        OpportunityItem::factory()->for($opportunity)->create([
            'version_id' => $target->id,
            'itemable_id' => $productKept->id,
            'itemable_type' => Product::class,
            'item_type' => OpportunityItemType::Product->value,
            'name' => 'Kept',
            'quantity' => 1,
            'unit_price' => 1000,
            'total' => 1000,
        ]);

        $diff = (new DiffVersions)($source->fresh(['opportunity']), $target->fresh(['opportunity']));

        expect($diff->removed)->toHaveCount(1)
            ->and($diff->removed[0]->item_id)->toBe($productRemoved->id)
            ->and($diff->removed[0]->source_total)->toBe('20.00')
            ->and($diff->added)->toBeEmpty()
            ->and($diff->changed)->toBeEmpty();
    });
});
