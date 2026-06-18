<?php

namespace App\Enums;

use App\Services\Opportunities\OpportunityTotalsCalculator;

/**
 * The nature of an opportunity-level cost line.
 *
 * Costs are ad-hoc charges that sit alongside the priced line items (delivery,
 * crew labour, surcharges, insurance, loss/damage recovery, etc.). Unlike line
 * items they are NOT priced by the rate engine — each carries its own `amount`.
 *
 * RMS-aligned integers persisted on `opportunity_costs.cost_type`. The type also
 * steers which opportunity money-total bucket the cost's net contributes to (see
 * {@see OpportunityTotalsCalculator}): delivery → the
 * transit total, loss/damage → the loss-damage total, everything else falls into
 * the general service total. Every cost (regardless of type) still rolls into the
 * tax-exclusive / tax / tax-inclusive / charge headline totals.
 */
enum OpportunityCostType: int
{
    case Delivery = 0;

    case Labour = 1;

    case Surcharge = 2;

    case Insurance = 3;

    case LossDamage = 4;

    case Misc = 5;

    public function label(): string
    {
        return match ($this) {
            self::Delivery => 'Delivery',
            self::Labour => 'Labour',
            self::Surcharge => 'Surcharge',
            self::Insurance => 'Insurance',
            self::LossDamage => 'Loss / Damage',
            self::Misc => 'Miscellaneous',
        };
    }
}
