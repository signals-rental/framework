<?php

use App\Enums\OpportunityItemType;
use App\Models\OpportunityItem;
use Illuminate\Support\Facades\Schema;

it('has the unified columns and no section/sort_order columns', function () {
    expect(Schema::hasColumns('opportunity_items', [
        'item_type', 'path', 'revenue_group_id', 'itemable_type', 'itemable_id',
    ]))->toBeTrue();
    expect(Schema::hasColumn('opportunity_items', 'section_id'))->toBeFalse();
    expect(Schema::hasColumn('opportunity_items', 'sort_order'))->toBeFalse();
    expect(Schema::hasTable('opportunity_sections'))->toBeFalse();
});

it('casts item_type and derives depth from path', function () {
    $item = OpportunityItem::factory()->make([
        'item_type' => OpportunityItemType::Product,
        'path' => '00010002',
    ]);
    expect($item->item_type)->toBe(OpportunityItemType::Product)
        ->and($item->depth())->toBe(2)
        ->and($item->parentPath())->toBe('0001');
});
