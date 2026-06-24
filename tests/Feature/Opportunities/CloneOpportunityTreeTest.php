<?php

use App\Actions\Opportunities\AddOpportunityGroup;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\CloneOpportunity;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\CreateVersion;
use App\Actions\Opportunities\RemoveOpportunityItem;
use App\Data\Opportunities\AddOpportunityGroupData;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\CreateVersionData;
use App\Enums\OpportunityItemType;
use App\Models\Opportunity;
use App\Models\OpportunityVersion;
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

/**
 * Build a source tree with a top-level path gap (0001, 0003) and a nested product
 * under the trailing group.
 *
 * @return array{0: Opportunity, 1: string, 2: string}
 */
function gappedTreeFixture(Store $store): array
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Gapped tree',
        'store_id' => $store->id,
        'starts_at' => '2026-08-01T09:00:00Z',
        'ends_at' => '2026-08-05T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

    (new AddOpportunityGroup)($opportunity, AddOpportunityGroupData::from(['name' => 'Front']));
    $front = $opportunity->refresh()->items()->where('path', '0001')->firstOrFail();

    (new AddOpportunityGroup)($opportunity->refresh(), AddOpportunityGroupData::from(['name' => 'Remove me']));
    $middle = $opportunity->refresh()->items()->where('path', '0002')->firstOrFail();

    (new AddOpportunityGroup)($opportunity->refresh(), AddOpportunityGroupData::from(['name' => 'Back']));
    $back = $opportunity->refresh()->items()->where('path', '0003')->firstOrFail();

    (new RemoveOpportunityItem)($middle->refresh());

    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => 'Nested line',
        'quantity' => '1',
        'unit_price' => 1000,
        'parent_path' => $back->path,
        'materialize_included_accessories' => false,
    ]));

    return [$opportunity->refresh(), $front->path, $back->path];
}

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

it('clones a gapped source tree and remaps nested children under their new parents', function () {
    [$source, $frontPath, $backPath] = gappedTreeFixture($this->store);

    expect($source->items()->orderBy('path')->pluck('path')->all())
        ->toBe([$frontPath, $backPath, $backPath.'0001']);

    $clone = (new CloneOpportunity)($source);
    $items = Opportunity::query()->whereKey($clone->id)->firstOrFail()->items()->orderBy('path')->get();

    expect($items)->toHaveCount(3)
        ->and($items[0]->item_type)->toBe(OpportunityItemType::Group)
        ->and($items[0]->path)->toBe('0001')
        ->and($items[1]->item_type)->toBe(OpportunityItemType::Group)
        ->and($items[1]->path)->toBe('0002')
        ->and($items[2]->item_type)->toBe(OpportunityItemType::Product)
        ->and($items[2]->path)->toBe('00020001');
});

it('creates a new version from a gapped source tree with remapped parent paths', function () {
    [$source, $frontPath, $backPath] = gappedTreeFixture($this->store);

    expect($source->items()->orderBy('path')->pluck('path')->all())
        ->toBe([$frontPath, $backPath, $backPath.'0001']);

    (new ConvertToQuotation)($source->refresh());

    $v1 = (new CreateVersion)($source->refresh(), CreateVersionData::from([]));
    $v2 = (new CreateVersion)($source->refresh(), CreateVersionData::from(['label' => 'Revision']));

    $items = OpportunityVersion::query()->whereKey($v2->id)->firstOrFail()->items()->orderBy('path')->get();

    expect($items)->toHaveCount(3)
        ->and($items[0]->path)->toBe('0001')
        ->and($items[1]->path)->toBe('0002')
        ->and($items[2]->path)->toBe('00020001')
        ->and($source->refresh()->active_version_id)->toBe($v2->id);
});
