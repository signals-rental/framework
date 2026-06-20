<?php

namespace App\Enums;

use ValueError;

/**
 * The workflow-position axis of the two-axis opportunity model.
 *
 * In the RMS-compatible schema `status` is a small integer that is only
 * meaningful relative to `state` — Quotation status `0` (Provisional) and Order
 * status `0` (Active) share the raw value `0`. To give domain code a single
 * type-safe enum while keeping the persisted columns RMS-aligned, each case is
 * backed by a globally-unique composite value of `state * 100 + statusValue`.
 *
 *  - {@see statusValue()} returns the per-state integer stored in
 *    `opportunities.status`.
 *  - {@see state()} returns the owning {@see OpportunityState}.
 *  - {@see fromStateAndStatus()} rebuilds the enum from the two columns.
 *  - {@see phase()} maps to the availability {@see DemandPhase} (consumed by
 *    Track B — the availability/demand engine).
 *
 * @see OpportunityState
 * @see DemandPhase
 */
enum OpportunityStatus: int
{
    // Draft (state = 0)
    case DraftOpen = 0;

    // Quotation (state = 1)
    case QuotationProvisional = 100;
    case QuotationReserved = 101;
    case QuotationLost = 102;
    case QuotationDead = 103;
    case QuotationPostponed = 104;

    // Order (state = 2)
    case OrderActive = 200;
    case OrderDispatched = 201;
    case OrderOnHire = 202;
    case OrderReturned = 203;
    case OrderChecked = 204;
    case OrderComplete = 205;
    case OrderCancelled = 206;

    /**
     * The owning document state for this status.
     */
    public function state(): OpportunityState
    {
        return OpportunityState::from(intdiv($this->value, 100));
    }

    /**
     * The per-state integer persisted in `opportunities.status`.
     */
    public function statusValue(): int
    {
        return $this->value % 100;
    }

    /**
     * Rebuild a status from the two persisted columns (`state`, `status`).
     *
     * @throws ValueError when the pair does not map to a known status
     */
    public static function fromStateAndStatus(OpportunityState $state, int $status): self
    {
        return self::from($state->value * 100 + $status);
    }

    public function label(): string
    {
        return match ($this) {
            self::DraftOpen => 'Open',
            self::QuotationProvisional => 'Provisional',
            self::QuotationReserved => 'Reserved',
            self::QuotationLost => 'Lost',
            self::QuotationDead => 'Dead',
            self::QuotationPostponed => 'Postponed',
            self::OrderActive => 'Active',
            self::OrderDispatched => 'Dispatched',
            self::OrderOnHire => 'On Hire',
            self::OrderReturned => 'Returned',
            self::OrderChecked => 'Checked',
            self::OrderComplete => 'Complete',
            self::OrderCancelled => 'Cancelled',
        };
    }

    /**
     * The availability {@see DemandPhase} this status places on the engine.
     *
     * Mapping follows the opportunity-status → demand-phase table in the
     * availability engine plan:
     *
     *  - Draft / provisional quotes → {@see DemandPhase::Draft} (inactive; the
     *    booking exists but does not consume stock).
     *  - Postponed quotes → {@see DemandPhase::Held} — a postponed reservation is
     *    soft-reserved, not released (availability-engine.md retains postponed
     *    demand as `held`). The unit stays claimed (active) while the deal is on
     *    hold.
     *  - Reserved quotes and active (confirmed, not-yet-dispatched) orders →
     *    {@see DemandPhase::Committed} (active).
     *  - Dispatched / on-hire orders → {@see DemandPhase::Operational} (active).
     *  - Returned / checked / completed orders → {@see DemandPhase::Closed}
     *    (inactive after turnaround) — but the exact point at which an order's
     *    operational demand closes is governed by the configurable
     *    {@see ReleasePoint} (availability-engine.md §"Configurable release point").
     *    See {@see $releasePoint} below.
     *  - Cancelled orders and lost / dead quotes → {@see DemandPhase::Void}
     *    (inactive immediately, no turnaround).
     *
     * The `release_point` (availability.release_point setting) governs which Order
     * sub-status FIRST drops to the Closed phase, freeing the unit (subject to
     * turnaround). It must be read from settings on the resolver/handle path and
     * passed in — never read inside an event's pure apply() — so phase derivation
     * stays replay-safe:
     *
     *  - {@see ReleasePoint::Returned} (default) — Returned closes the demand. This
     *    is the historical behaviour and is preserved when no point is supplied.
     *  - {@see ReleasePoint::OffHired} — release at/before physical return. The
     *    framework has no distinct Off-hired Order status, so this maps to the
     *    same Returned boundary (documented choice — avoids inventing a status).
     *  - {@see ReleasePoint::Checked} — strict: a Returned order stays
     *    {@see DemandPhase::Operational} (the unit still occupies) until it is
     *    Checked/Complete, which closes it.
     */
    public function phase(?ReleasePoint $releasePoint = null): DemandPhase
    {
        $releasePoint ??= ReleasePoint::default();

        return match ($this) {
            self::DraftOpen,
            self::QuotationProvisional => DemandPhase::Draft,

            self::QuotationPostponed => DemandPhase::Held,

            self::QuotationReserved,
            self::OrderActive => DemandPhase::Committed,

            self::OrderDispatched,
            self::OrderOnHire => DemandPhase::Operational,

            // The Returned boundary depends on the configured release point: with
            // the strict `checked` point a Returned order is still considered to
            // occupy the unit (Operational) until inspection clears it; otherwise
            // a physical return closes the demand.
            self::OrderReturned => $releasePoint === ReleasePoint::Checked
                ? DemandPhase::Operational
                : DemandPhase::Closed,

            self::OrderChecked,
            self::OrderComplete => DemandPhase::Closed,

            self::QuotationLost,
            self::QuotationDead,
            self::OrderCancelled => DemandPhase::Void,
        };
    }

    /**
     * Whether this status is terminal for editing — the opportunity can no
     * longer be mutated through the standard update/transition events.
     */
    public function isClosed(): bool
    {
        return match ($this) {
            self::OrderComplete,
            self::OrderCancelled,
            self::QuotationLost,
            self::QuotationDead => true,
            default => false,
        };
    }

    /**
     * Whether an opportunity in this status can be REINSTATED back to an active
     * status (opportunity-lifecycle.md §5.2 OpportunityReinstated: "Status must be
     * Lost, Dead, Postponed, or Cancelled").
     *
     * Derived generically from the demand phase rather than a named-status matrix
     * (Ben's locked steer), so configurable/custom statuses inherit the predicate:
     *
     *  - Void-phase statuses (Lost / Dead / Cancelled) — abandoned but recoverable.
     *  - Held-phase statuses (Postponed) — parked on hold, resumable.
     *
     * The terminal "complete" close is deliberately NOT reinstatable here — it has
     * its own `OpportunityReopened` path (§5.2), out of this transition's scope.
     */
    public function isReinstatable(): bool
    {
        return $this->phase() === DemandPhase::Void
            || $this->phase() === DemandPhase::Held;
    }

    /**
     * Whether this status is the terminal "complete" close of the order
     * lifecycle — the point at which all assets must already be finalised/returned
     * (opportunity-lifecycle.md §5.2: OpportunityCompleted "all assets finalised or
     * no assets").
     *
     * Derived generically: it is the closed/terminal status of the Order state
     * that is NOT a Void-phase status (cancellation). Custom statuses that derive
     * from Complete inherit the predicate through their phase + closed mapping
     * rather than being name-matched here.
     */
    public function isTerminalComplete(): bool
    {
        return $this->isClosed()
            && $this->state() === OpportunityState::Order
            && $this->phase() !== DemandPhase::Void;
    }
}
