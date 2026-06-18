<?php

use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\CreateOpportunityData;
use App\Models\Opportunity;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
});

it('generates a zero-padded number on create', function () {
    $store = Store::factory()->create();

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'First', 'store_id' => $store->id,
    ]));

    expect(Opportunity::query()->whereKey($created->id)->value('number'))->toBe('0000000001');
});

it('hands out sequential numbers within a store', function () {
    $store = Store::factory()->create();

    $a = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'A', 'store_id' => $store->id]));
    $b = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'B', 'store_id' => $store->id]));

    expect(Opportunity::query()->whereKey($a->id)->value('number'))->toBe('0000000001')
        ->and(Opportunity::query()->whereKey($b->id)->value('number'))->toBe('0000000002');
});

it('scopes the running number per store', function () {
    $storeA = Store::factory()->create();
    $storeB = Store::factory()->create();

    $a1 = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'A1', 'store_id' => $storeA->id]));
    $b1 = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'B1', 'store_id' => $storeB->id]));
    $a2 = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'A2', 'store_id' => $storeA->id]));

    // Each store keeps its own sequence, so both stores start at 1.
    expect(Opportunity::query()->whereKey($a1->id)->value('number'))->toBe('0000000001')
        ->and(Opportunity::query()->whereKey($b1->id)->value('number'))->toBe('0000000001')
        ->and(Opportunity::query()->whereKey($a2->id)->value('number'))->toBe('0000000002');
});

it('honours an overridden number_pad width from settings', function () {
    settings()->set('opportunities.number_pad', 4, 'integer');

    $store = Store::factory()->create();

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Padded', 'store_id' => $store->id,
    ]));

    // Width follows the setting (4) rather than the default (10).
    expect(Opportunity::query()->whereKey($created->id)->value('number'))->toBe('0001');
});

it('shares one sequence across stores when number_scope is global', function () {
    settings()->set('opportunities.number_scope', 'global');

    $storeA = Store::factory()->create();
    $storeB = Store::factory()->create();

    $a = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'A', 'store_id' => $storeA->id]));
    $b = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'B', 'store_id' => $storeB->id]));

    // Global scope: the second opportunity continues the same running number even
    // though it belongs to a different store.
    expect(Opportunity::query()->whereKey($a->id)->value('number'))->toBe('0000000001')
        ->and(Opportunity::query()->whereKey($b->id)->value('number'))->toBe('0000000002');
});

it('reproduces the same number on replay', function () {
    $store = Store::factory()->create();

    $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'Replayed', 'store_id' => $store->id]));
    $numberBefore = Opportunity::query()->whereKey($created->id)->value('number');

    expect($numberBefore)->toBe('0000000001');

    // Replay re-applies the stored OpportunityCreated payload (which carries the
    // baked-in number); it must NOT re-allocate from the sequence.
    Verbs::replay();

    expect(Opportunity::query()->whereKey($created->id)->value('number'))->toBe($numberBefore);
});
