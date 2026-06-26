<?php

use App\Actions\Opportunities\AcceptVersion;
use App\Actions\Opportunities\ActivateVersion;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ChangeItemQuantity;
use App\Actions\Opportunities\ChangeOpportunityStatus;
use App\Actions\Opportunities\ChangeVersionLabel;
use App\Actions\Opportunities\CloneOpportunity;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Actions\Opportunities\CreateVersion;
use App\Actions\Opportunities\DeclineVersion;
use App\Actions\Opportunities\DeleteVersion;
use App\Actions\Opportunities\DiffVersions;
use App\Actions\Opportunities\OverrideItemPrice;
use App\Actions\Opportunities\RemoveOpportunityItem;
use App\Actions\Opportunities\SendVersion;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\ChangeItemQuantityData;
use App\Data\Opportunities\ChangeVersionLabelData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\CreateVersionData;
use App\Data\Opportunities\OverrideItemPriceData;
use App\Enums\OpportunityStatus;
use App\Enums\VersionStatus;
use App\Enums\VersionType;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityVersion;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Validation\ValidationException;
use Thunk\Verbs\Exceptions\EventNotValid;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actor = User::factory()->owner()->create();
    $this->actingAs($this->actor);
    $this->store = Store::factory()->create();
});

/**
 * Build a Quotation opportunity carrying a single manual-priced line.
 *
 * @return array{0: Opportunity, 1: OpportunityItem}
 */
function versionedQuotationWithItem(?Store $store = null): array
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Versions',
        'store_id' => $store?->id,
        'starts_at' => '2026-09-01T09:00:00Z',
        'ends_at' => '2026-09-05T17:00:00Z',
    ]));

    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => 'PA Stack',
        'quantity' => '2',
        'unit_price' => 5000,
    ]));

    (new ConvertToQuotation)($opportunity->refresh());

    return [$opportunity->refresh(), $opportunity->allItems()->firstOrFail()];
}

it('creates the first version, adopts the existing items, and activates it', function () {
    [$opportunity] = versionedQuotationWithItem();

    $version = (new CreateVersion)($opportunity, CreateVersionData::from([]));
    $opportunity->refresh();

    expect($version->version_number)->toBe(1)
        ->and($version->version_type)->toBe(VersionType::Revision->value)
        ->and($version->is_active)->toBeTrue()
        ->and($opportunity->active_version_id)->toBe($version->id)
        ->and($opportunity->version_count)->toBe(1);

    // The opportunity's existing item was adopted into version 1.
    $items = OpportunityVersion::query()->whereKey($version->id)->firstOrFail()->items;
    expect($items)->toHaveCount(1)
        ->and($items->first()->total)->toBe(40000) // 2 x 5000 x 4 chargeable days
        ->and($opportunity->charge_total)->toBe(40000);
});

it('creates a revision that clones items and supersedes its parent', function () {
    [$opportunity] = versionedQuotationWithItem();

    $v1 = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from([]));
    $v2 = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from(['label' => 'Revised']));
    $opportunity->refresh();

    expect($v2->version_number)->toBe(2)
        ->and($v2->parent_version_id)->toBe($v1->id)
        ->and($v2->is_active)->toBeTrue()
        ->and($opportunity->active_version_id)->toBe($v2->id);

    // The parent revision is superseded; v2's cloned items are independent.
    expect(OpportunityVersion::query()->whereKey($v1->id)->firstOrFail()->status)->toBe(VersionStatus::Superseded)
        ->and(OpportunityVersion::query()->whereKey($v2->id)->firstOrFail()->items)->toHaveCount(1);

    // Cloned items are independent copies (distinct ids, same priced total).
    $v1ItemId = OpportunityVersion::query()->whereKey($v1->id)->firstOrFail()->items->first()->id;
    $v2ItemId = OpportunityVersion::query()->whereKey($v2->id)->firstOrFail()->items->first()->id;
    expect($v2ItemId)->not->toBe($v1ItemId)
        ->and($opportunity->charge_total)->toBe(40000);
});

it('creates an alternative that coexists and flags has_alternatives', function () {
    [$opportunity] = versionedQuotationWithItem();

    (new CreateVersion)($opportunity->refresh(), CreateVersionData::from([]));
    $alt = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from([
        'version_type' => VersionType::Alternative->value,
        'label' => 'Budget option',
    ]));
    $opportunity->refresh();

    expect($alt->version_type)->toBe(VersionType::Alternative->value)
        ->and($alt->parent_version_id)->toBeNull()
        ->and($opportunity->has_alternatives)->toBeTrue();

    // The first version is NOT superseded by an alternative.
    $first = OpportunityVersion::query()->where('version_number', 1)->firstOrFail();
    expect($first->status)->toBe(VersionStatus::Draft);
});

it('keeps exactly one active version and switches the opportunity totals on activation', function () {
    [$opportunity] = versionedQuotationWithItem();

    $v1 = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from([]));
    // v2 is an alternative; add a second line so its total differs.
    $v2 = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from(['version_type' => VersionType::Alternative->value]));
    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => 'Extra', 'quantity' => '1', 'unit_price' => 3000,
    ]));
    $opportunity->refresh();

    // v2 active -> totals = 40000 (cloned) + 12000 (1 x 3000 x 4 days) = 52000.
    expect($opportunity->active_version_id)->toBe($v2->id)
        ->and($opportunity->charge_total)->toBe(52000);

    (new ActivateVersion)(OpportunityVersion::query()->whereKey($v1->id)->firstOrFail());
    $opportunity->refresh();

    expect($opportunity->active_version_id)->toBe($v1->id)
        ->and($opportunity->charge_total)->toBe(40000);

    // The one-active invariant holds.
    expect(OpportunityVersion::query()->where('opportunity_id', $opportunity->id)->where('is_active', true)->count())->toBe(1);
});

it('swaps demands to the active version on activation', function () {
    [$opportunity] = versionedQuotationWithItem($this->store);
    $productA = Product::factory()->rental()->bulk()->create();
    $productB = Product::factory()->rental()->bulk()->create();

    // Replace the manual line with a product line so it raises demand.
    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => $productA->name, 'itemable_id' => $productA->id, 'itemable_type' => Product::class, 'quantity' => '2',
    ]));

    $v1 = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from([]));
    (new ChangeOpportunityStatus)($opportunity->refresh(), OpportunityStatus::QuotationReserved);

    $v1Items = OpportunityVersion::query()->whereKey($v1->id)->firstOrFail()->items;
    expect(Demand::query()->whereIn('source_id', $v1Items->pluck('id'))->where('is_active', true)->count())->toBeGreaterThan(0);

    // Alternative v2 with product B becomes active.
    $v2 = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from(['version_type' => VersionType::Alternative->value]));
    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => $productB->name, 'itemable_id' => $productB->id, 'itemable_type' => Product::class, 'quantity' => '1',
    ]));
    $opportunity->refresh();

    // v1's demands are released; v2's demands are synced active.
    expect(Demand::query()->whereIn('source_id', $v1Items->pluck('id'))->where('is_active', true)->count())->toBe(0);
    $v2Items = OpportunityVersion::query()->whereKey($v2->id)->firstOrFail()->items;
    expect(Demand::query()->whereIn('source_id', $v2Items->pluck('id'))->where('is_active', true)->count())->toBeGreaterThan(0);

    // Reactivate v1 -> demands swap back.
    (new ActivateVersion)(OpportunityVersion::query()->whereKey($v1->id)->firstOrFail());
    expect(Demand::query()->whereIn('source_id', $v1Items->pluck('id'))->where('is_active', true)->count())->toBeGreaterThan(0)
        ->and(Demand::query()->whereIn('source_id', $v2Items->pluck('id'))->where('is_active', true)->count())->toBe(0);
});

it('sends, accepts, and declines a version through the workflow', function () {
    [$opportunity] = versionedQuotationWithItem();
    $v1 = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from([]));

    (new SendVersion)(OpportunityVersion::query()->whereKey($v1->id)->firstOrFail());
    $sent = OpportunityVersion::query()->whereKey($v1->id)->firstOrFail();
    expect($sent->status)->toBe(VersionStatus::Sent)->and($sent->sent_at)->not->toBeNull();

    (new AcceptVersion)($sent);
    $accepted = OpportunityVersion::query()->whereKey($v1->id)->firstOrFail();
    expect($accepted->status)->toBe(VersionStatus::Accepted)->and($accepted->accepted_at)->not->toBeNull();

    // Declining a fresh second version.
    $v2 = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from(['version_type' => VersionType::Alternative->value]));
    (new DeclineVersion)(OpportunityVersion::query()->whereKey($v2->id)->firstOrFail());
    expect(OpportunityVersion::query()->whereKey($v2->id)->firstOrFail()->status)->toBe(VersionStatus::Declined);
});

it('changes a version label', function () {
    [$opportunity] = versionedQuotationWithItem();
    $v1 = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from(['label' => 'First']));

    (new ChangeVersionLabel)(OpportunityVersion::query()->whereKey($v1->id)->firstOrFail(), ChangeVersionLabelData::from(['label' => 'Renamed']));

    expect(OpportunityVersion::query()->whereKey($v1->id)->firstOrFail()->label)->toBe('Renamed');
});

it('rejects sending an already-sent version', function () {
    [$opportunity] = versionedQuotationWithItem();
    $v1 = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from([]));
    (new SendVersion)(OpportunityVersion::query()->whereKey($v1->id)->firstOrFail());

    expect(fn () => (new SendVersion)(OpportunityVersion::query()->whereKey($v1->id)->firstOrFail()))
        ->toThrow(EventNotValid::class);
});

it('enforces the maximum version count', function () {
    [$opportunity] = versionedQuotationWithItem();
    settings()->set('opportunities.max_versions', 2);

    (new CreateVersion)($opportunity->refresh(), CreateVersionData::from([]));
    (new CreateVersion)($opportunity->refresh(), CreateVersionData::from([]));

    expect(fn () => (new CreateVersion)($opportunity->refresh(), CreateVersionData::from([])))
        ->toThrow(EventNotValid::class);
});

it('enforces the maximum concurrent alternatives', function () {
    [$opportunity] = versionedQuotationWithItem();
    settings()->set('opportunities.max_alternatives', 1);

    (new CreateVersion)($opportunity->refresh(), CreateVersionData::from([]));
    (new CreateVersion)($opportunity->refresh(), CreateVersionData::from(['version_type' => VersionType::Alternative->value]));

    expect(fn () => (new CreateVersion)($opportunity->refresh(), CreateVersionData::from(['version_type' => VersionType::Alternative->value])))
        ->toThrow(EventNotValid::class);
});

it('rejects creating a version on a non-quotation opportunity', function () {
    $created = (new CreateOpportunity)(CreateOpportunityData::from(['subject' => 'Draft']));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

    // Still a Draft (not yet quoted).
    expect(fn () => (new CreateVersion)($opportunity, CreateVersionData::from([])))
        ->toThrow(EventNotValid::class);
});

it('deletes a non-active, non-only version and its items', function () {
    [$opportunity] = versionedQuotationWithItem();
    $v1 = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from([]));
    $v2 = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from(['version_type' => VersionType::Alternative->value]));

    // v2 is active; activate v1 so v2 becomes deletable.
    (new ActivateVersion)(OpportunityVersion::query()->whereKey($v1->id)->firstOrFail());

    $v2Items = OpportunityVersion::query()->whereKey($v2->id)->firstOrFail()->items->pluck('id');
    (new DeleteVersion)(OpportunityVersion::query()->whereKey($v2->id)->firstOrFail());

    expect(OpportunityVersion::query()->whereKey($v2->id)->exists())->toBeFalse()
        ->and(OpportunityItem::query()->whereIn('id', $v2Items)->count())->toBe(0);
});

it('rejects deleting the active version', function () {
    [$opportunity] = versionedQuotationWithItem();
    (new CreateVersion)($opportunity->refresh(), CreateVersionData::from([]));
    $v2 = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from(['version_type' => VersionType::Alternative->value]));

    // v2 is the active version.
    expect(fn () => (new DeleteVersion)(OpportunityVersion::query()->whereKey($v2->id)->firstOrFail()))
        ->toThrow(EventNotValid::class);
});

it('rejects deleting the only version', function () {
    [$opportunity] = versionedQuotationWithItem();
    $v1 = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from([]));

    expect(fn () => (new DeleteVersion)(OpportunityVersion::query()->whereKey($v1->id)->firstOrFail()))
        ->toThrow(EventNotValid::class);
});

it('confirms an accepted version at convert-to-order, superseding the rest', function () {
    [$opportunity] = versionedQuotationWithItem();
    $v1 = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from([]));
    $v2 = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from(['version_type' => VersionType::Alternative->value]));

    // v2 is active, but v1 is accepted -> conversion confirms v1.
    (new AcceptVersion)(OpportunityVersion::query()->whereKey($v1->id)->firstOrFail());
    (new ConvertToOrder)($opportunity->refresh());
    $opportunity->refresh();

    expect($opportunity->active_version_id)->toBe($v1->id)
        ->and(OpportunityVersion::query()->whereKey($v1->id)->firstOrFail()->status)->toBe(VersionStatus::Accepted)
        ->and(OpportunityVersion::query()->whereKey($v2->id)->firstOrFail()->status)->toBe(VersionStatus::Superseded);
});

it('falls back to the active version at convert-to-order when none is accepted', function () {
    [$opportunity] = versionedQuotationWithItem();
    (new CreateVersion)($opportunity->refresh(), CreateVersionData::from([]));
    $v2 = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from(['version_type' => VersionType::Alternative->value]));

    (new ConvertToOrder)($opportunity->refresh());
    $opportunity->refresh();

    expect($opportunity->active_version_id)->toBe($v2->id)
        ->and(OpportunityVersion::query()->whereKey($v2->id)->firstOrFail()->status)->not->toBe(VersionStatus::Superseded);
});

it('diffs two versions: added, removed, changed, and net change', function () {
    // Build an opportunity whose lines are all PRODUCT-backed, so the diff can
    // match them by product across versions (ad-hoc lines have no stable identity
    // across versions and always read as removed+added).
    $productChanged = Product::factory()->rental()->bulk()->create();
    $productRemoved = Product::factory()->rental()->bulk()->create();
    $productAdded = Product::factory()->rental()->bulk()->create();

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Diff', 'starts_at' => '2026-09-01T09:00:00Z', 'ends_at' => '2026-09-05T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();
    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $productChanged->name, 'itemable_id' => $productChanged->id, 'itemable_type' => Product::class, 'quantity' => '2', 'unit_price' => 5000,
    ]));
    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => $productRemoved->name, 'itemable_id' => $productRemoved->id, 'itemable_type' => Product::class, 'quantity' => '1', 'unit_price' => 2000,
    ]));
    (new ConvertToQuotation)($opportunity->refresh());

    $v1 = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from([]));

    // v2 is a revision; change the first line's quantity, remove the second, add a
    // new product line.
    $v2 = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from([]));
    $v2Model = OpportunityVersion::query()->whereKey($v2->id)->firstOrFail();
    $changedLine = $v2Model->items->firstWhere('itemable_id', $productChanged->id);
    $removedLine = $v2Model->items->firstWhere('itemable_id', $productRemoved->id);

    (new ChangeItemQuantity)($changedLine->refresh(), ChangeItemQuantityData::from(['quantity' => '4']));
    // Re-assert the manual unit price (a product line reprices via the rate engine
    // on a quantity change, which would drop the manual override to 0).
    (new OverrideItemPrice)($changedLine->refresh(), OverrideItemPriceData::from(['unit_price' => 5000]));
    (new RemoveOpportunityItem)($removedLine->refresh());
    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => $productAdded->name, 'itemable_id' => $productAdded->id, 'itemable_type' => Product::class, 'quantity' => '1', 'unit_price' => 1000,
    ]));

    $diff = (new DiffVersions)(
        OpportunityVersion::query()->whereKey($v1->id)->firstOrFail(),
        OpportunityVersion::query()->whereKey($v2->id)->firstOrFail(),
    );

    expect($diff->added)->toHaveCount(1)
        ->and($diff->removed)->toHaveCount(1)
        ->and($diff->changed)->toHaveCount(1)
        ->and($diff->added[0]->item_id)->toBe($productAdded->id)
        ->and($diff->removed[0]->item_id)->toBe($productRemoved->id)
        ->and($diff->changed[0]->source_quantity)->toBe('2.00')
        ->and($diff->changed[0]->target_quantity)->toBe('4.00');

    // Net change: v1 = 40000 + 8000 = 48000; v2 = 80000 (4*5000*4) + 4000 (1*1000*4) = 84000.
    expect($diff->source_total)->toBe('480.00')
        ->and($diff->target_total)->toBe('840.00')
        ->and($diff->net_change)->toBe('360.00');
});

it('rejects diffing versions from different opportunities', function () {
    [$opportunityA] = versionedQuotationWithItem();
    [$opportunityB] = versionedQuotationWithItem();
    $vA = (new CreateVersion)($opportunityA->refresh(), CreateVersionData::from([]));
    $vB = (new CreateVersion)($opportunityB->refresh(), CreateVersionData::from([]));

    expect(fn () => (new DiffVersions)(
        OpportunityVersion::query()->whereKey($vA->id)->firstOrFail(),
        OpportunityVersion::query()->whereKey($vB->id)->firstOrFail(),
    ))->toThrow(ValidationException::class);
});

it('scopes items() to the active version under both eager and lazy loading', function () {
    [$opportunity] = versionedQuotationWithItem();

    // v1 (revision) clones the single adopted line; v2 (revision) clones it again.
    // The active version is v2.
    (new CreateVersion)($opportunity->refresh(), CreateVersionData::from([]));
    $v2 = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from([]));
    $opportunity->refresh();

    expect($opportunity->active_version_id)->toBe($v2->id);

    // Across all versions there are 2 item rows (v1 + v2), but the active version
    // (v2) only owns 1.
    expect(OpportunityItem::query()->count())->toBe(2);

    // EAGER path — the bug: a constraint-less newInstance() skipped the dynamic
    // guard and leaked every version's items, matched by opportunity_id alone.
    $eager = Opportunity::query()->with('items')->findOrFail($opportunity->id);
    expect($eager->items)->toHaveCount(1)
        ->and($eager->items->first()->version_id)->toBe($v2->id);

    // load() / loadMissing() / fresh() take the same eager path.
    $loaded = Opportunity::query()->findOrFail($opportunity->id);
    $loaded->load('items');
    expect($loaded->items)->toHaveCount(1);

    // withCount() must count active-only too.
    $counted = Opportunity::query()->withCount('items')->findOrFail($opportunity->id);
    expect($counted->items_count)->toBe(1);

    // LAZY path returns the same set as the eager path.
    $lazy = Opportunity::query()->findOrFail($opportunity->id);
    expect($lazy->items)->toHaveCount(1)
        ->and($lazy->items->pluck('id')->sort()->values()->all())
        ->toBe($eager->items->pluck('id')->sort()->values()->all());
});

it('leaves a non-versioned opportunity\'s eager items() unchanged', function () {
    // No versions created — items carry version_id = NULL, active_version_id = 0.
    [$opportunity, $item] = versionedQuotationWithItem();

    expect($opportunity->active_version_id)->toBe(0)
        ->and($item->version_id)->toBeNull();

    $eager = Opportunity::query()->with('items')->findOrFail($opportunity->id);
    $lazy = Opportunity::query()->findOrFail($opportunity->id);

    expect($eager->items)->toHaveCount(1)
        ->and($lazy->items)->toHaveCount(1)
        ->and($eager->items->first()->id)->toBe($item->id);
});

it('clones only the active version\'s items into a non-versioned draft', function () {
    [$opportunity] = versionedQuotationWithItem();

    // v1 adopts the line, v2 (active) clones it. Two item rows exist, but only v2
    // is active.
    (new CreateVersion)($opportunity->refresh(), CreateVersionData::from([]));
    $v2 = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from([]));
    $opportunity->refresh();
    expect($opportunity->active_version_id)->toBe($v2->id);

    $clone = (new CloneOpportunity)($opportunity);
    $cloneModel = Opportunity::query()->whereKey($clone->id)->firstOrFail();

    // The clone is a fresh, non-versioned Draft.
    expect($cloneModel->active_version_id)->toBe(0)
        ->and($cloneModel->version_count)->toBe(0);

    // It carries exactly the active version's item count (1), all version_id = NULL.
    $cloneItems = $cloneModel->allItems()->get();
    expect($cloneItems)->toHaveCount(1)
        ->and($cloneItems->every(fn ($i) => $i->version_id === null))->toBeTrue();
});

it('diffs two lines of the same product without collapsing them', function () {
    // v1 has product A twice (qty 2 @5000, qty 1 @3000); v2 changes the second
    // occurrence's quantity. Per-line keying must keep both lines distinct so the
    // diff reports exactly one CHANGED line and totals reconcile.
    $productA = Product::factory()->rental()->bulk()->create();

    $created = (new CreateOpportunity)(CreateOpportunityData::from([
        'subject' => 'Multi-line', 'starts_at' => '2026-09-01T09:00:00Z', 'ends_at' => '2026-09-05T17:00:00Z',
    ]));
    $opportunity = Opportunity::query()->whereKey($created->id)->firstOrFail();

    (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
        'name' => $productA->name, 'itemable_id' => $productA->id, 'itemable_type' => Product::class,
        'quantity' => '2', 'unit_price' => 5000,
    ]));
    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => $productA->name, 'itemable_id' => $productA->id, 'itemable_type' => Product::class,
        'quantity' => '1', 'unit_price' => 3000,
    ]));
    (new ConvertToQuotation)($opportunity->refresh());

    $v1 = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from([]));
    $v2 = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from([]));

    // Re-pin BOTH cloned lines to their manual prices (a product line reprices via
    // the rate engine on clone, which would otherwise read as a spurious change),
    // then change ONLY the second occurrence (sort_order 2) in v2.
    $v2Model = OpportunityVersion::query()->whereKey($v2->id)->firstOrFail();
    $v2Lines = $v2Model->items()->orderBy('sort_order')->get();
    $firstLine = $v2Lines->first();
    $secondLine = $v2Lines->last();
    (new OverrideItemPrice)($firstLine->refresh(), OverrideItemPriceData::from(['unit_price' => 5000]));
    (new ChangeItemQuantity)($secondLine->refresh(), ChangeItemQuantityData::from(['quantity' => '5']));
    (new OverrideItemPrice)($secondLine->refresh(), OverrideItemPriceData::from(['unit_price' => 3000]));

    $diff = (new DiffVersions)(
        OpportunityVersion::query()->whereKey($v1->id)->firstOrFail(),
        OpportunityVersion::query()->whereKey($v2->id)->firstOrFail(),
    );

    // Exactly one changed line, no dropped line (no added/removed).
    expect($diff->added)->toHaveCount(0)
        ->and($diff->removed)->toHaveCount(0)
        ->and($diff->changed)->toHaveCount(1)
        ->and($diff->changed[0]->source_quantity)->toBe('1.00')
        ->and($diff->changed[0]->target_quantity)->toBe('5.00');

    // Totals reconcile: v1 = (2*5000 + 1*3000) x 4 days = 52000; v2 = (2*5000 + 5*3000) x 4 = 100000.
    expect($diff->source_total)->toBe('520.00')
        ->and($diff->target_total)->toBe('1000.00')
        ->and($diff->net_change)->toBe('480.00');
});

it('persists the decline reason and supersession lineage', function () {
    [$opportunity] = versionedQuotationWithItem();
    $v1 = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from([]));
    // v2 supersedes v1 (a revision) and records the forward pointer.
    $v2 = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from([]));

    expect(OpportunityVersion::query()->whereKey($v1->id)->firstOrFail()->superseded_by_version_id)->toBe($v2->id);

    // Decline an alternative with a reason.
    $alt = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from(['version_type' => VersionType::Alternative->value]));
    (new DeclineVersion)(OpportunityVersion::query()->whereKey($alt->id)->firstOrFail(), 'Customer chose a cheaper option');

    expect(OpportunityVersion::query()->whereKey($alt->id)->firstOrFail()->decline_reason)
        ->toBe('Customer chose a cheaper option');
});

it('rejects sending or accepting a version on a converted (Order) opportunity', function () {
    [$opportunity] = versionedQuotationWithItem();
    $v1 = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from([]));

    // Convert to an Order — the opportunity leaves the Quotation state.
    (new ConvertToOrder)($opportunity->refresh());

    expect(fn () => (new SendVersion)(OpportunityVersion::query()->whereKey($v1->id)->firstOrFail()))
        ->toThrow(EventNotValid::class);

    expect(fn () => (new AcceptVersion)(OpportunityVersion::query()->whereKey($v1->id)->firstOrFail()))
        ->toThrow(EventNotValid::class);
});

it('rebuilds the version tree, items, and opportunity state identically on replay', function () {
    [$opportunity] = versionedQuotationWithItem();
    $v1 = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from(['label' => 'v1']));
    $v2 = (new CreateVersion)($opportunity->refresh(), CreateVersionData::from(['label' => 'v2']));
    (new CreateVersion)($opportunity->refresh(), CreateVersionData::from(['version_type' => VersionType::Alternative->value]));
    (new ActivateVersion)(OpportunityVersion::query()->whereKey($v1->id)->firstOrFail());

    $versionsBefore = OpportunityVersion::query()->orderBy('id')
        ->get(['id', 'version_number', 'version_type', 'status', 'is_active', 'parent_version_id'])->toArray();
    $itemsBefore = OpportunityItem::query()->orderBy('id')->get(['id', 'version_id', 'total'])->toArray();
    $opportunityBefore = Opportunity::query()->whereKey($opportunity->id)->firstOrFail()
        ->only(['active_version_id', 'version_count', 'has_alternatives', 'charge_total']);

    OpportunityItem::query()->forceDelete();
    OpportunityVersion::query()->delete();
    Opportunity::query()->forceDelete();

    Verbs::replay();

    expect(OpportunityVersion::query()->orderBy('id')->get(['id', 'version_number', 'version_type', 'status', 'is_active', 'parent_version_id'])->toArray())->toBe($versionsBefore)
        ->and(OpportunityItem::query()->orderBy('id')->get(['id', 'version_id', 'total'])->toArray())->toBe($itemsBefore)
        ->and(Opportunity::query()->whereKey($opportunity->id)->firstOrFail()->only(['active_version_id', 'version_count', 'has_alternatives', 'charge_total']))->toBe($opportunityBefore);
});
