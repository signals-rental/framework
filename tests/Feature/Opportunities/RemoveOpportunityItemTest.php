<?php

use App\Actions\Opportunities\AddOpportunityGroup;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\RemoveOpportunityItem;
use App\Data\Opportunities\AddOpportunityGroupData;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
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

function removeTestOpportunity(Store $store): Opportunity
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Remove cascade',
        'store_id' => $store->id,
        'starts_at' => '2026-07-01T09:00:00Z',
        'ends_at' => '2026-07-05T17:00:00Z',
    ]));

    return Opportunity::query()->whereKey($created->id)->firstOrFail();
}

it('removes a single product line without touching siblings', function () {
    $opportunity = removeTestOpportunity($this->store);

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Keep',
        'quantity' => '1',
        'unit_price' => 1000,
    ]));
    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => 'Remove me',
        'quantity' => '1',
        'unit_price' => 1000,
    ]));

    $items = $opportunity->refresh()->items()->orderBy('id')->get();
    $survivor = $items[0];
    $target = $items[1];

    (new RemoveOpportunityItem)($target);

    expect(OpportunityItem::query()->where('opportunity_id', $opportunity->id)->count())->toBe(1)
        ->and(OpportunityItem::query()->whereKey($survivor->id)->exists())->toBeTrue()
        ->and(OpportunityItem::query()->whereKey($target->id)->exists())->toBeFalse();
});

it('cascades removal of a group and its entire subtree deepest-first', function () {
    $opportunity = removeTestOpportunity($this->store);

    (new AddOpportunityGroup)($opportunity, AddOpportunityGroupData::from(['name' => 'Lighting']));
    $group = $opportunity->refresh()->items()->where('path', '0001')->firstOrFail();

    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => 'PAR',
        'quantity' => '1',
        'unit_price' => 1000,
        'parent_path' => $group->path,
    ]));
    $product = $opportunity->refresh()->items()->where('path', '00010001')->firstOrFail();

    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => 'Sibling',
        'quantity' => '1',
        'unit_price' => 500,
    ]));

    (new RemoveOpportunityItem)($group->refresh());

    expect(OpportunityItem::query()->where('opportunity_id', $opportunity->id)->count())->toBe(1)
        ->and(OpportunityItem::query()->whereKey($product->id)->exists())->toBeFalse()
        ->and(OpportunityItem::query()->where('path', '0002')->exists())->toBeTrue();
});
