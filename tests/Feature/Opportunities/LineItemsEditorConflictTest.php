<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Models\Opportunity;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();
    $this->actingAs($this->owner);
});

function conflictTestOpportunity(): Opportunity
{
    Auth::login(test()->owner);

    $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'Conflict test']));

    return Opportunity::query()->whereKey($created->id)->firstOrFail();
}

it('returns stale when persistTree base revision is behind the server', function () {
    $opportunity = conflictTestOpportunity();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Line A',
        'quantity' => '1',
        'unit_price' => 1000,
    ]));

    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh()]);
    $revision = lineItemsEditorInstance($component)->treeRevision();

    (new AddOpportunityItem)($opportunity->fresh(), AddOpportunityItemData::from([
        'name' => 'Line B',
        'quantity' => '1',
        'unit_price' => 500,
    ]));

    $items = $opportunity->fresh(['items'])->items;
    $nodes = $items->map(fn ($item): array => ['id' => $item->id, 'depth' => $item->depth()])->all();

    $result = lineItemsEditorInstance($component)->persistTree($nodes, $revision);

    expect($result['stale'])->toBeTrue()
        ->and($result['revision'])->toBeGreaterThan($revision);
});

it('pullTree reconciles local pending rows against fresh server truth', function () {
    $opportunity = conflictTestOpportunity();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Server line',
        'quantity' => '1',
        'unit_price' => 1000,
    ]));

    $component = Volt::test('opportunities.line-items', ['opportunity' => $opportunity->fresh()]);
    $item = $opportunity->fresh(['items'])->items->first();

    $localRows = [[
        'id' => $item->id,
        'path' => $item->path,
        'name' => 'Pending local rename',
        'quantity' => '1',
        'unit_price' => 1000,
        'discount_percent' => null,
    ]];

    $pull = lineItemsEditorInstance($component)->pullTree(0, $localRows, [$item->id]);

    expect($pull['tree'])->not->toBeEmpty()
        ->and($pull['conflicts'])->toHaveKey($item->id);
});
