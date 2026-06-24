<?php

use App\Data\Opportunities\OpportunityItemData;
use App\Enums\OpportunityItemType;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;

it('serialises the unified RMS line-item shape from a model', function () {
    $opportunity = Opportunity::factory()->create();
    $product = Product::factory()->create();

    $item = OpportunityItem::factory()->for($opportunity)->create([
        'itemable_id' => $product->id,
        'itemable_type' => Product::class,
        'item_type' => OpportunityItemType::Product->value,
        'path' => '00010001',
        'revenue_group_id' => 42,
        'name' => 'Line A',
    ]);

    $data = OpportunityItemData::fromModel($item->fresh());
    $output = $data->toArray();

    expect($output)->toHaveKeys([
        'id', 'opportunity_id', 'version_id', 'item_id', 'itemable_type', 'item_type',
        'path', 'parent_path', 'depth', 'revenue_group_id', 'name', 'quantity',
        'unit_price', 'total', 'created_at', 'updated_at',
    ])
        ->and($output)->not->toHaveKey('sort_order')
        ->and($output)->not->toHaveKey('section_id')
        ->and($output['item_id'])->toBe($product->id)
        ->and($output['itemable_type'])->toBe(Product::class)
        ->and($output['item_type'])->toBe('product')
        ->and($output['path'])->toBe('00010001')
        ->and($output['parent_path'])->toBe('0001')
        ->and($output['depth'])->toBe(2)
        ->and($output['revenue_group_id'])->toBe(42);
});

it('maps a top-level group row with null morph and parent path', function () {
    $item = OpportunityItem::factory()->create([
        'itemable_id' => null,
        'itemable_type' => null,
        'item_type' => OpportunityItemType::Group->value,
        'path' => '0002',
        'revenue_group_id' => null,
        'name' => 'Audio',
    ]);

    $output = OpportunityItemData::fromModel($item->fresh())->toArray();

    expect($output['item_id'])->toBeNull()
        ->and($output['itemable_type'])->toBeNull()
        ->and($output['item_type'])->toBe('group')
        ->and($output['path'])->toBe('0002')
        ->and($output['parent_path'])->toBeNull()
        ->and($output['depth'])->toBe(1)
        ->and($output['revenue_group_id'])->toBeNull();
});
