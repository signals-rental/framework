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
 *  - {@see phase()} maps to the availability demand phase (consumed by Track B).
 *
 * @see OpportunityState
 * @see AvailabilityPhase
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
     * The availability demand phase this status places on the engine.
     */
    public function phase(): AvailabilityPhase
    {
        return match ($this) {
            self::DraftOpen,
            self::QuotationProvisional,
            self::QuotationLost,
            self::QuotationDead,
            self::QuotationPostponed,
            self::OrderComplete,
            self::OrderCancelled => AvailabilityPhase::None,

            self::QuotationReserved => AvailabilityPhase::Soft,

            self::OrderActive,
            self::OrderReturned,
            self::OrderChecked => AvailabilityPhase::Confirmed,

            self::OrderDispatched,
            self::OrderOnHire => AvailabilityPhase::OnHire,
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
}
