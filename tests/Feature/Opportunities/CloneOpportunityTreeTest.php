<?php

use App\Actions\Opportunities\AddOpportunityGroup;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\CloneOpportunity;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\AddOpportunityGroupData;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\OpportunityItemType;
use App\Models\Opportunity;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
    $this->store = Store::factory()->create();
});

it('clones the full nested tree including group rows and child paths', function () {
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Tree source',
        'store_id' => $this->store->id,
        'starts_at' => '2026-08-01T09:00:00Z',
        'ends_at' => '2026-08-05T17:00:00Z',
    ]));
    $source = Opportunity::query()->whereKey($created->id)->firstOrFail();

    (new AddOpportunityGroup)($source, AddOpportunityGroupData::from(['name' => 'Rig']));
    $group = $source->refresh()->items()->where('path', '0001')->firstOrFail();

    (new AddOpportunityItem)($source->refresh(), AddOpportunityItemData::from([
        'name' => 'Line',
        'quantity' => '1',
        'unit_price' => 1000,
        'parent_path' => $group->path,
        'materialize_included_accessories' => false,
    ]));

    $clone = (new CloneOpportunity)($source->refresh());
    $cloneModel = Opportunity::query()->whereKey($clone->id)->firstOrFail();
    $items = $cloneModel->items()->orderBy('path')->get();

    expect($items)->toHaveCount(2)
        ->and($items[0]->item_type)->toBe(OpportunityItemType::Group)
        ->and($items[0]->path)->toBe('0001')
        ->and($items[1]->item_type)->toBe(OpportunityItemType::Product)
        ->and($items[1]->path)->toBe('00010001');
});
