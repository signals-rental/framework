<?php

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ChangeItemQuantity;
use App\Actions\Opportunities\ClearDealPrice;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\OverrideItemPrice;
use App\Actions\Opportunities\RemoveOpportunityItem;
use App\Actions\Opportunities\SetDealPrice;
use App\Actions\Opportunities\SetItemDiscount;
use App\Actions\Opportunities\ToggleItemOptional;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\ChangeItemQuantityData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\OverrideItemPriceData;
use App\Data\Opportunities\SetDealPriceData;
use App\Data\Opportunities\SetItemDiscountData;
use App\Data\Opportunities\ToggleItemOptionalData;
use App\Models\ActionLog;
use App\Models\Opportunity;
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

function auditOpportunity(): Opportunity
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'Audited items']));

    return Opportunity::query()->whereKey($created->id)->firstOrFail();
}

it('writes an audit row against the opportunity for each item mutation', function () {
    $opportunity = auditOpportunity();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Speaker', 'quantity' => '2', 'unit_price' => 5000,
    ]));
    $item = $opportunity->items()->firstOrFail();

    (new ChangeItemQuantity)($item->refresh(), ChangeItemQuantityData::from(['quantity' => '3']));
    (new OverrideItemPrice)($item->refresh(), OverrideItemPriceData::from(['unit_price' => 6000]));
    (new SetItemDiscount)($item->refresh(), SetItemDiscountData::from(['discount_percent' => '5']));
    (new ToggleItemOptional)($item->refresh(), ToggleItemOptionalData::from(['is_optional' => true]));
    (new SetDealPrice)($opportunity->refresh(), SetDealPriceData::from(['deal_total' => 9000]));
    (new ClearDealPrice)($opportunity->refresh());

    $actions = ActionLog::query()
        ->where('auditable_type', Opportunity::class)
        ->where('auditable_id', $opportunity->id)
        ->pluck('action')
        ->all();

    expect($actions)->toContain('opportunity.item_added')
        ->toContain('opportunity.item_quantity_changed')
        ->toContain('opportunity.item_price_overridden')
        ->toContain('opportunity.item_discount_set')
        ->toContain('opportunity.item_optional_toggled')
        ->toContain('opportunity.deal_price_set')
        ->toContain('opportunity.deal_price_cleared');

    // Every item-audit row carries the verb_event_id (replay dedup key).
    $itemAdded = ActionLog::query()
        ->where('auditable_id', $opportunity->id)
        ->where('action', 'opportunity.item_added')
        ->sole();
    expect($itemAdded->verb_event_id)->not->toBeNull()
        ->and($itemAdded->user_id)->toBe($this->actor->id);
});

it('records a removal audit row with the removed item snapshot', function () {
    $opportunity = auditOpportunity();
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Cable', 'quantity' => '1', 'unit_price' => 1000,
    ]));
    $item = $opportunity->items()->firstOrFail();

    (new RemoveOpportunityItem)($item->refresh());

    $removed = ActionLog::query()
        ->where('auditable_id', $opportunity->id)
        ->where('action', 'opportunity.item_removed')
        ->sole();

    /** @var array<string, mixed>|null $oldValues */
    $oldValues = $removed->old_values;

    expect($removed->new_values)->toBeNull();
    expect($oldValues)->not->toBeNull();
    expect($oldValues['name'] ?? null)->toBe('Cable');
});

it('does not duplicate item audit rows on replay', function () {
    $opportunity = auditOpportunity();
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'Truss', 'quantity' => '2', 'unit_price' => 4000,
    ]));
    $item = $opportunity->items()->firstOrFail();
    (new ChangeItemQuantity)($item->refresh(), ChangeItemQuantityData::from(['quantity' => '5']));

    $countBefore = ActionLog::query()->where('auditable_id', $opportunity->id)->count();

    Verbs::replay();

    expect(ActionLog::query()->where('auditable_id', $opportunity->id)->count())->toBe($countBefore);
});
