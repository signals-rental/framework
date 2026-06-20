<?php

namespace App\Verbs\Events\Opportunities\Concerns;

use App\Enums\AssetAssignmentStatus;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\OpportunityItemAsset;
use Brick\Math\BigDecimal;
use Thunk\Verbs\Event;

/**
 * Generic, phase/capability-keyed lifecycle invariant guards for opportunity
 * transition events (opportunity-lifecycle.md §12.1 "Built-in Guards").
 *
 * These are HARD structural rules expressed against the projection (an aggregate
 * read, allowed in validate()/handle() — never in apply()). They are deliberately
 * NOT a named-status matrix: callers feed in the TARGET status's phase /
 * capability and the predicate answers from the physical asset state. This keeps
 * the rules valid for configurable/custom statuses that derive their phase from a
 * default one.
 *
 *  - {@see opportunityHasActiveItem()} — at least one (active-version) line item
 *    exists; the convert-to-order guard rejects an empty deal.
 *  - {@see opportunityHasStockOut()} — any asset is physically Dispatched/OnHire,
 *    or any bulk line has `dispatched_quantity > returned_quantity`; the
 *    cancel-into-Void guard rejects voiding a deal whose stock is still out.
 *  - {@see opportunityHasUnreturnedAssets()} — any asset is not yet
 *    Finalised/returned (Dispatched/OnHire still out, or CheckedIn awaiting a
 *    check), or any bulk line still has stock out; the complete guard rejects
 *    completing a deal whose stock has not all come back.
 *
 * @mixin Event
 */
trait GuardsOpportunityLifecycle
{
    /**
     * Whether the opportunity has at least one active-version line item.
     *
     * For a versioned opportunity this counts only the ACTIVE version's items
     * (the version that will actually ship — opportunity-lifecycle.md §8.8), via
     * the {@see Opportunity::items()} relation which is already active-version
     * scoped. Falls back to the projection so it is robust against a stale
     * in-memory `item_count`.
     */
    protected function opportunityHasActiveItem(int $stateId): bool
    {
        $opportunity = $this->opportunityForState($stateId);

        if ($opportunity === null) {
            return false;
        }

        return $opportunity->items()->exists();
    }

    /**
     * Whether any of the opportunity's assets are physically OUT (a serialised
     * asset in Dispatched/OnHire, or a bulk line with more dispatched than
     * returned). Used to block a transition into the Void phase
     * (cancel/lost/dead) while stock is with the client.
     */
    protected function opportunityHasStockOut(int $stateId): bool
    {
        $opportunity = $this->opportunityForState($stateId);

        if ($opportunity === null) {
            return false;
        }

        $assetsOut = OpportunityItemAsset::query()
            ->whereIn(
                'opportunity_item_id',
                $opportunity->allItems()->select('opportunity_items.id'),
            )
            ->whereIn('status', [
                AssetAssignmentStatus::Dispatched->value,
                AssetAssignmentStatus::OnHire->value,
            ])
            ->exists();

        if ($assetsOut) {
            return true;
        }

        return $this->anyBulkLineOutstanding($opportunity);
    }

    /**
     * Whether any of the opportunity's assets have NOT yet been finalised /
     * returned: a serialised asset still Dispatched/OnHire (out) or CheckedIn
     * (returned but not yet checked), or a bulk line with stock still out. Used to
     * block the terminal "complete" transition until everything is back.
     */
    protected function opportunityHasUnreturnedAssets(int $stateId): bool
    {
        $opportunity = $this->opportunityForState($stateId);

        if ($opportunity === null) {
            return false;
        }

        $unreturned = OpportunityItemAsset::query()
            ->whereIn(
                'opportunity_item_id',
                $opportunity->allItems()->select('opportunity_items.id'),
            )
            ->whereIn('status', [
                AssetAssignmentStatus::Dispatched->value,
                AssetAssignmentStatus::OnHire->value,
                AssetAssignmentStatus::CheckedIn->value,
            ])
            ->exists();

        if ($unreturned) {
            return true;
        }

        return $this->anyBulkLineOutstanding($opportunity);
    }

    /**
     * Whether the opportunity has ANY dispatch history — a serialised asset that
     * has reached (or passed) Dispatched, or a bulk line with `dispatched_quantity`
     * greater than zero. Unlike {@see opportunityHasStockOut()} (which asks only
     * whether stock is currently OUT), this also catches stock that was dispatched
     * and has since come back. Used to block reverting an Order to a Quotation: a
     * job that has begun fulfilment can never be un-ordered (§5.2
     * OpportunityRevertedToQuote: "nothing dispatched").
     */
    protected function opportunityHasDispatchHistory(int $stateId): bool
    {
        $opportunity = $this->opportunityForState($stateId);

        if ($opportunity === null) {
            return false;
        }

        $assetDispatched = OpportunityItemAsset::query()
            ->whereIn(
                'opportunity_item_id',
                $opportunity->allItems()->select('opportunity_items.id'),
            )
            ->where('status', '>=', AssetAssignmentStatus::Dispatched->value)
            ->exists();

        if ($assetDispatched) {
            return true;
        }

        return $opportunity->allItems()->get(['dispatched_quantity'])
            ->contains(function (OpportunityItem $item): bool {
                return BigDecimal::of((string) ($item->dispatched_quantity ?? '0'))->isGreaterThan(0);
            });
    }

    /**
     * Whether any bulk (non-serialised) line still has stock out —
     * `dispatched_quantity` exceeds `returned_quantity`.
     */
    protected function anyBulkLineOutstanding(Opportunity $opportunity): bool
    {
        return $opportunity->allItems()->get(['dispatched_quantity', 'returned_quantity'])
            ->contains(function (OpportunityItem $item): bool {
                $dispatched = BigDecimal::of((string) ($item->dispatched_quantity ?? '0'));
                $returned = BigDecimal::of((string) ($item->returned_quantity ?? '0'));

                return $dispatched->isGreaterThan($returned);
            });
    }

    /**
     * Load the opportunity projection for a given Verbs state id (validate() and
     * handle() both run after the prior event's projection write, so the row
     * reflects the pre-mutation state).
     */
    protected function opportunityForState(int $stateId): ?Opportunity
    {
        return Opportunity::query()->where('state_id', $stateId)->first();
    }
}
