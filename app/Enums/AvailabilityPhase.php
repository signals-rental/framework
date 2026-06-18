<?php

namespace App\Enums;

/**
 * The demand phase an opportunity status places on the availability engine.
 *
 * Each opportunity status maps to exactly one phase. The availability engine
 * (Track B) reads this mapping to decide whether — and how strongly — an
 * opportunity consumes stock. Custom tenant statuses declare which default
 * status they derive from, inheriting that status's phase.
 *
 * @see OpportunityStatus::phase()
 */
enum AvailabilityPhase: string
{
    /** No demand created (Draft, Lost, Dead). */
    case None = 'none';

    /** Soft demand — a reservation that may not convert (Reserved quotes). */
    case Soft = 'soft';

    /** Confirmed demand — a committed order not yet dispatched. */
    case Confirmed = 'confirmed';

    /** Stock physically out with the client (Dispatched, On Hire). */
    case OnHire = 'on_hire';

    public function label(): string
    {
        return match ($this) {
            self::None => 'No Demand',
            self::Soft => 'Reserved',
            self::Confirmed => 'Confirmed',
            self::OnHire => 'On Hire',
        };
    }

    /**
     * The availability_demands.demand_type value this phase produces, or null
     * when the phase creates no demand row at all.
     */
    public function demandType(): ?string
    {
        return match ($this) {
            self::None => null,
            self::Soft => 'reserved',
            self::Confirmed => 'confirmed',
            self::OnHire => 'on_hire',
        };
    }
}
