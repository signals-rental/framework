<?php

namespace App\Verbs\Events\Opportunities\Concerns;

use App\Enums\AssetAssignmentStatus;
use App\Enums\OpportunityState;
use App\Enums\OpportunityStatus;
use App\Enums\StockMethod;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Services\Opportunities\AutoPromotionContext;
use App\Verbs\Events\Opportunities\OpportunityStatusPromoted;
use Brick\Math\BigDecimal;
use Thunk\Verbs\Event;
use Thunk\Verbs\Lifecycle\Broker;

/**
 * Derives an Order's aggregate fulfilment sub-status from the projected state of
 * all its line items and auto-promotes the opportunity when that derived status
 * differs from the current one (opportunity-lifecycle.md §7.2 / §7.6 / §7.7).
 *
 * Called from an asset/bulk event's `fired()` hook — NOT from apply()/handle().
 * Verbs runs `fired()` after apply() but before commit/handle, only in the
 * original request (never on replay; see {@see Broker}).
 * The promotion is therefore persisted as its own {@see OpportunityStatusPromoted}
 * event and replays independently — the asset event never re-fires it on replay.
 *
 * Because `fired()` runs BEFORE any handle() in the commit projects its row, the
 * just-changed asset/bulk projections are still stale at derivation time — and in a
 * BATCH commit, every event's `fired()` runs before ANY handle(), so siblings are
 * stale too. Callers pass an {@see Overlay} describing the in-flight rows' NEW state
 * (one entry for a single op, the whole batch for {@see QuickBookOut} /
 * {@see QuickCheckIn}) so the derivation reflects the true post-commit aggregate.
 *
 * §7.6 aggregate-status derivation (the "lowest common denominator" across all
 * items, only meaningful once the opportunity is an Order):
 *
 *   IF any item still has allocated-but-undispatched units  → Dispatched
 *   ELSE IF any item has dispatched/on-hire (unreturned) units → On Hire
 *   ELSE IF any item has returned-but-unchecked units        → Returned
 *   ELSE (everything dispatched is checked)                  → Checked
 *
 * "units" spans BOTH item types: serialised lines count their per-asset assignment
 * rows by status; bulk lines compare dispatched/returned quantities. A line that
 * has dispatched nothing at all contributes nothing — it is simply still Active
 * and does not by itself force a promotion.
 *
 * The derivation reads OTHER items' projection rows, so it must run in
 * `fired()`/`handle()`, never in the single-state `apply()`.
 *
 * @phpstan-type Overlay array{
 *     assignment_statuses?: array<int, AssetAssignmentStatus>,
 *     bulk_lines?: array<int, array{dispatched: string, returned: string}>,
 * }
 *
 * @mixin Event
 */
trait PromotesOpportunityStatus
{
    /**
     * Re-derive the opportunity's Order sub-status from its items and fire an
     * {@see OpportunityStatusPromoted} event when it has changed. Idempotent: a
     * no-op when the derived status equals the current one, so it never fires a
     * redundant promotion.
     *
     * Only Orders auto-promote — a quote has no dispatch/return axis. Closed
     * opportunities are left untouched (the promotion event would reject them
     * anyway).
     *
     * @param  Overlay  $overlay  in-flight row state not yet projected, applied on
     *                            top of the stored rows during derivation
     */
    protected function promoteOpportunityFromItems(?Opportunity $opportunity, array $overlay = []): void
    {
        if ($opportunity === null) {
            return;
        }

        // Inside a batch wrapper the per-event promotion is suppressed; the wrapper
        // fires one final authoritative promotion with the whole batch overlaid.
        if (app(AutoPromotionContext::class)->isSuppressed()) {
            return;
        }

        $opportunity = $opportunity->fresh();

        if ($opportunity === null) {
            return;
        }

        $current = $opportunity->statusEnum();

        // Auto-promotion is an Order-only fulfilment axis; quotes and closed
        // opportunities never auto-promote.
        if ($current->state() !== OpportunityState::Order || $current->isClosed()) {
            return;
        }

        $derived = $this->deriveOrderStatus($opportunity, $overlay);

        if ($derived === null || $derived === $current) {
            return;
        }

        OpportunityStatusPromoted::fire(
            // The promotion's StateId is the opportunity's snowflake state_id, not
            // the small projection PK (mirrors ChangeOpportunityStatus).
            opportunity_id: $opportunity->state_id,
            to_status: $derived->statusValue(),
        );
    }

    /**
     * Convenience for the single-asset events: build a one-entry overlay.
     *
     * @return Overlay
     */
    protected function singleAssetOverlay(int $assignmentId, AssetAssignmentStatus $status): array
    {
        return ['assignment_statuses' => [$assignmentId => $status]];
    }

    /**
     * Convenience for the bulk events: build a one-line overlay.
     *
     * @return Overlay
     */
    protected function singleBulkOverlay(int $itemId, string $dispatched, string $returned): array
    {
        return ['bulk_lines' => [$itemId => ['dispatched' => $dispatched, 'returned' => $returned]]];
    }

    /**
     * Derive the §7.6 aggregate Order status from every line item, or null when no
     * item has progressed past Active (nothing dispatched yet → leave Active).
     *
     * @param  Overlay  $overlay
     */
    private function deriveOrderStatus(Opportunity $opportunity, array $overlay): ?OpportunityStatus
    {
        $hasUndispatched = false;
        $hasUnreturned = false;
        $hasUncheckedReturn = false;
        $anyDispatched = false;

        foreach ($opportunity->items()->with('assets')->get() as $item) {
            $tally = $this->tallyItem($item, $overlay);

            $hasUndispatched = $hasUndispatched || $tally['undispatched'];
            $hasUnreturned = $hasUnreturned || $tally['unreturned'];
            $hasUncheckedReturn = $hasUncheckedReturn || $tally['unchecked_return'];
            $anyDispatched = $anyDispatched || $tally['any_dispatched'];
        }

        // Nothing has been dispatched anywhere → the order is still Active; no
        // promotion is implied.
        if (! $anyDispatched) {
            return null;
        }

        if ($hasUndispatched) {
            return OpportunityStatus::OrderDispatched;
        }

        if ($hasUnreturned) {
            return OpportunityStatus::OrderOnHire;
        }

        if ($hasUncheckedReturn) {
            return OpportunityStatus::OrderReturned;
        }

        return OpportunityStatus::OrderChecked;
    }

    /**
     * Tally one line item's fulfilment position. Serialised lines inspect their
     * per-asset assignment rows; bulk lines compare dispatched/returned
     * quantities.
     *
     * @param  Overlay  $overlay
     * @return array{undispatched: bool, unreturned: bool, unchecked_return: bool, any_dispatched: bool}
     */
    private function tallyItem(OpportunityItem $item, array $overlay): array
    {
        if ($this->isSerialised($item)) {
            return $this->tallySerialised($item, $overlay);
        }

        return $this->tallyBulk($item, $overlay);
    }

    /**
     * @param  Overlay  $overlay
     * @return array{undispatched: bool, unreturned: bool, unchecked_return: bool, any_dispatched: bool}
     */
    private function tallySerialised(OpportunityItem $item, array $overlay): array
    {
        $undispatched = false;
        $unreturned = false;
        $uncheckedReturn = false;
        $anyDispatched = false;

        $statusOverlay = $overlay['assignment_statuses'] ?? [];

        foreach ($item->assets as $asset) {
            $status = $asset->status;

            // Overlay the in-flight status onto any assignment whose projection has
            // not been written yet (its own event, or a sibling in the same batch).
            if (array_key_exists((int) $asset->id, $statusOverlay)) {
                $status = $statusOverlay[(int) $asset->id];
            }

            match ($status) {
                // Allocated/Prepared assets are committed but not yet out: they
                // hold the order at Dispatched until they leave the building.
                AssetAssignmentStatus::Allocated,
                AssetAssignmentStatus::Prepared => $undispatched = true,
                AssetAssignmentStatus::Dispatched,
                AssetAssignmentStatus::OnHire => $unreturned = true,
                AssetAssignmentStatus::CheckedIn => $uncheckedReturn = true,
                AssetAssignmentStatus::Finalised => null,
            };

            if (in_array($status, [
                AssetAssignmentStatus::Dispatched,
                AssetAssignmentStatus::OnHire,
                AssetAssignmentStatus::CheckedIn,
                AssetAssignmentStatus::Finalised,
            ], true)) {
                $anyDispatched = true;
            }
        }

        return [
            'undispatched' => $undispatched,
            'unreturned' => $unreturned,
            'unchecked_return' => $uncheckedReturn,
            'any_dispatched' => $anyDispatched,
        ];
    }

    /**
     * @param  Overlay  $overlay
     * @return array{undispatched: bool, unreturned: bool, unchecked_return: bool, any_dispatched: bool}
     */
    private function tallyBulk(OpportunityItem $item, array $overlay): array
    {
        $dispatched = BigDecimal::of((string) ($item->dispatched_quantity ?? '0'));
        $returned = BigDecimal::of((string) ($item->returned_quantity ?? '0'));

        $bulkOverlay = $overlay['bulk_lines'][(int) $item->id] ?? null;

        if ($bulkOverlay !== null) {
            $dispatched = BigDecimal::of($bulkOverlay['dispatched']);
            $returned = BigDecimal::of($bulkOverlay['returned']);
        }

        $anyDispatched = $dispatched->isGreaterThan(0);
        $outstanding = $dispatched->minus($returned);

        return [
            // Bulk lines have no Allocated→Prepared sub-states to leave undispatched
            // beyond what has not yet been dispatched; partial dispatch alone does
            // not block On Hire (a 60/40 split is still "on hire" for what is out).
            'undispatched' => false,
            // Anything dispatched and not yet returned is still out on hire.
            'unreturned' => $outstanding->isGreaterThan(0),
            // Bulk returns are immediately "checked" — there is no per-unit
            // inspection gate — so a returned bulk quantity never sits in the
            // unchecked-return state.
            'unchecked_return' => false,
            'any_dispatched' => $anyDispatched,
        ];
    }

    private function isSerialised(OpportunityItem $item): bool
    {
        $product = $item->item_type !== null && $item->item_id !== null
            ? Product::query()->find($item->item_id)
            : null;

        return $product?->stock_method === StockMethod::Serialised;
    }
}
