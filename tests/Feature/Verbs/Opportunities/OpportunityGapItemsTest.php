<?php

use App\Actions\Opportunities\AddOpportunityCost;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\CloneOpportunity;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\AddOpportunityCostData;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Enums\OpportunityState;
use App\Enums\OpportunityStatus;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\Opportunities\OpportunityTotalsCalculator;
use App\Verbs\Events\Opportunities\OpportunityStatusChanged;
use App\Verbs\Events\Opportunities\OpportunityStatusPromoted;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Thunk\Verbs\Exceptions\EventNotValidForCurrentState;
use Thunk\Verbs\Facades\Verbs;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

/**
 * @param  array<string, mixed>  $attributes
 */
function makeGapOpportunity(array $attributes = []): Opportunity
{
    $created = (new CreateOpportunity)(CreateOpportunityData::from(array_merge(['subject' => 'Source'], $attributes)));

    return Opportunity::query()->whereKey($created->id)->firstOrFail();
}

/**
 * Add a single manual line item to a quotation so it can be converted to an
 * order (opportunity-lifecycle.md §12.1 convert guard requires ≥ 1 item).
 */
function addGapLine(Opportunity $opportunity): void
{
    (new AddOpportunityItem)($opportunity->refresh(), AddOpportunityItemData::from([
        'name' => 'Line',
        'quantity' => '1',
        'unit_price' => 5000,
        'starts_at' => now()->toIso8601String(),
        'ends_at' => now()->addDays(2)->toIso8601String(),
    ]));
}

// ---------------------------------------------------------------------------
// Build 1 — CloneOpportunity
// ---------------------------------------------------------------------------

it('clones an opportunity into a new draft with its items and costs', function () {
    $source = makeGapOpportunity(['reference' => 'PO-SRC', 'currency' => 'GBP']);
    (new ConvertToQuotation)($source);
    // An order must carry at least one line item to be confirmed
    // (opportunity-lifecycle.md §12.1 convert guard) — add it while still a quote.
    (new AddOpportunityItem)($source->refresh(), AddOpportunityItemData::from([
        'name' => 'PA Stack',
        'quantity' => '2',
        'unit_price' => 5000,
        'starts_at' => now()->toIso8601String(),
        'ends_at' => now()->addDays(2)->toIso8601String(),
    ]));
    (new ConvertToOrder)(Opportunity::findOrFail($source->id));
    $source->refresh();

    (new AddOpportunityCost)($source, AddOpportunityCostData::from([
        'description' => 'Delivery',
        'amount' => 2500,
    ]));

    $source->refresh()->load(['items', 'costs']);

    $clone = (new CloneOpportunity)($source);

    // Landed as a Draft regardless of the source being an Order.
    expect($clone->state)->toBe(OpportunityState::Draft->value)
        ->and($clone->status)->toBe(OpportunityStatus::DraftOpen->statusValue())
        ->and($clone->id)->not->toBe($source->id);

    // Items + costs were copied (read from the projection rows).
    $cloneModel = Opportunity::findOrFail($clone->id)->load(['items', 'costs']);
    expect($cloneModel->items)->toHaveCount(1)
        ->and($cloneModel->costs)->toHaveCount(1)
        ->and($cloneModel->items[0]->name)->toBe('PA Stack')
        ->and($cloneModel->costs[0]->description)->toBe('Delivery');

    // A fresh, distinct number was allocated.
    $sourceNumber = Opportunity::findOrFail($source->id)->number;
    $cloneNumber = $cloneModel->number;
    expect($cloneNumber)->not->toBeNull()->and($cloneNumber)->not->toBe($sourceNumber);

    // Header detail was carried across; locks were NOT copied (fresh draft).
    expect($cloneModel->reference)->toBe('PO-SRC')
        ->and($cloneModel->exchange_rate_locked)->toBeFalse()
        ->and($cloneModel->tax_locked)->toBeFalse();
});

it('rebuilds demand for cloned items', function () {
    $source = makeGapOpportunity();
    (new AddOpportunityItem)($source, AddOpportunityItemData::from([
        'name' => 'Lighting',
        'quantity' => '3',
        'unit_price' => 1000,
        'starts_at' => now()->toIso8601String(),
        'ends_at' => now()->addDay()->toIso8601String(),
    ]));
    $source->refresh()->load(['items', 'costs']);

    $clone = (new CloneOpportunity)($source);

    // The cloned item exists with its own demand-syncing event having fired (the
    // ItemAdded handler syncs demand). The projected line carries the same qty.
    $cloneItem = Opportunity::findOrFail($clone->id)->items()->firstOrFail();
    expect((float) $cloneItem->quantity)->toBe(3.0);
});

it('records the clone lineage in the audit trail', function () {
    $source = makeGapOpportunity();
    $clone = (new CloneOpportunity)($source);

    $this->assertDatabaseHas('action_logs', [
        'auditable_type' => Opportunity::class,
        'auditable_id' => $clone->id,
        'action' => 'opportunity.cloned',
    ]);
});

it('rebuilds the clone identically on replay', function () {
    $source = makeGapOpportunity();
    (new AddOpportunityItem)($source, AddOpportunityItemData::from([
        'name' => 'Replayable item',
        'quantity' => '2',
        'unit_price' => 4000,
        'starts_at' => now()->toIso8601String(),
        'ends_at' => now()->addDay()->toIso8601String(),
    ]));
    $source->refresh()->load(['items', 'costs']);

    $cloneData = (new CloneOpportunity)($source);
    $cloneId = $cloneData->id;

    $before = Opportunity::findOrFail($cloneId)->only(['id', 'state', 'status', 'subject']);
    $itemsBefore = Opportunity::findOrFail($cloneId)->items()->orderBy('id')
        ->get(['id', 'name', 'quantity', 'total'])->toArray();

    Opportunity::query()->withTrashed()->forceDelete();
    expect(Opportunity::query()->withTrashed()->count())->toBe(0);

    Verbs::replay();

    $after = Opportunity::findOrFail($cloneId)->only(['id', 'state', 'status', 'subject']);
    $itemsAfter = Opportunity::findOrFail($cloneId)->items()->orderBy('id')
        ->get(['id', 'name', 'quantity', 'total'])->toArray();

    expect($after)->toEqual($before)
        ->and($itemsAfter)->toEqual($itemsBefore);
});

it('appends (cloned) to the cloned opportunity subject', function () {
    $source = makeGapOpportunity();

    $clone = Opportunity::findOrFail((new CloneOpportunity)($source)->id);

    expect($clone->subject)->toBe($source->subject.' (cloned)');
});

it('denies cloning without the create permission', function () {
    $source = makeGapOpportunity();

    $this->actingAs(User::factory()->create());

    (new CloneOpportunity)($source);
})->throws(AuthorizationException::class);

// ---------------------------------------------------------------------------
// Build 2 — FX / tax locks
// ---------------------------------------------------------------------------

it('locks the exchange rate and tax when converting to an order', function () {
    $source = makeGapOpportunity();
    (new ConvertToQuotation)($source);
    addGapLine($source);

    $opportunity = Opportunity::findOrFail($source->id);
    expect($opportunity->exchange_rate_locked)->toBeFalse()
        ->and($opportunity->tax_locked)->toBeFalse();

    (new ConvertToOrder)(Opportunity::findOrFail($source->id));

    $ordered = Opportunity::findOrFail($source->id);
    expect($ordered->exchange_rate_locked)->toBeTrue()
        ->and($ordered->tax_locked)->toBeTrue();
});

it('preserves the locked tax total when totals are recomputed after lock', function () {
    $source = makeGapOpportunity();
    (new ConvertToQuotation)($source);
    addGapLine($source);
    (new ConvertToOrder)(Opportunity::findOrFail($source->id));

    $ordered = Opportunity::findOrFail($source->id);
    expect($ordered->tax_locked)->toBeTrue();

    // Stamp a known locked tax figure, then run the calculator's rollUp. A tax-rule
    // change after lock must NOT move the stored tax — rollUp skips the final tax
    // pass while tax_locked is set.
    $ordered->forceFill(['tax_total' => 9999, 'charge_total' => 50000])->saveQuietly();

    app(OpportunityTotalsCalculator::class)->rollUp($ordered->fresh());

    $recomputed = Opportunity::findOrFail($source->id);
    expect((int) $recomputed->tax_total)->toBe(9999)
        ->and((int) $recomputed->charge_including_tax_total)->toBe((int) $recomputed->charge_total + 9999);
});

it('keeps the locks set across replay', function () {
    $source = makeGapOpportunity();
    (new ConvertToQuotation)($source);
    addGapLine($source);
    (new ConvertToOrder)(Opportunity::findOrFail($source->id));
    $id = $source->id;

    Opportunity::query()->withTrashed()->forceDelete();
    Verbs::replay();

    $rebuilt = Opportunity::findOrFail($id);
    expect($rebuilt->exchange_rate_locked)->toBeTrue()
        ->and($rebuilt->tax_locked)->toBeTrue();
});

// ---------------------------------------------------------------------------
// Build 3 — OpportunityStatusPromoted (scaffold)
// ---------------------------------------------------------------------------

it('promotes status, writes an audit row, and is replay-stable when fired directly', function () {
    // The event has no trigger yet (M5); fire it directly to prove the scaffold.
    $source = makeGapOpportunity();
    (new ConvertToQuotation)($source);
    addGapLine($source);
    (new ConvertToOrder)(Opportunity::findOrFail($source->id));
    $ordered = Opportunity::findOrFail($source->id);

    expect($ordered->status)->toBe(OpportunityStatus::OrderActive->statusValue());

    OpportunityStatusPromoted::fire(
        opportunity_id: $ordered->state_id,
        to_status: OpportunityStatus::OrderDispatched->statusValue(),
    );
    Verbs::commit();

    $promoted = Opportunity::findOrFail($source->id);
    expect($promoted->status)->toBe(OpportunityStatus::OrderDispatched->statusValue());

    $this->assertDatabaseHas('action_logs', [
        'auditable_type' => Opportunity::class,
        'auditable_id' => $source->id,
        'action' => 'opportunity.status_promoted',
    ]);

    // Replay-stable: wipe and rebuild from the event store.
    Opportunity::query()->withTrashed()->forceDelete();
    Verbs::replay();

    expect(Opportunity::findOrFail($source->id)->status)
        ->toBe(OpportunityStatus::OrderDispatched->statusValue());
});

it('rejects promoting to a status invalid for the current state', function () {
    $source = makeGapOpportunity();
    $draft = Opportunity::findOrFail($source->id);

    // Draft only has status 0; an Order status is not valid while Draft.
    OpportunityStatusPromoted::fire(
        opportunity_id: $draft->state_id,
        to_status: OpportunityStatus::OrderDispatched->statusValue(),
    );
    Verbs::commit();
})->throws(EventNotValidForCurrentState::class);

it('rejects promoting a closed opportunity', function () {
    $source = makeGapOpportunity();
    (new ConvertToQuotation)($source);
    $quote = Opportunity::findOrFail($source->id);

    // Move to a terminal status (Lost) first.
    OpportunityStatusChanged::fire(
        opportunity_id: $quote->state_id,
        to_status: OpportunityStatus::QuotationLost->statusValue(),
    );
    Verbs::commit();

    OpportunityStatusPromoted::fire(
        opportunity_id: $quote->state_id,
        to_status: OpportunityStatus::QuotationReserved->statusValue(),
    );
    Verbs::commit();
})->throws(EventNotValidForCurrentState::class);
