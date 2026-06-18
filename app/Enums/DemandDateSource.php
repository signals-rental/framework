<?php

namespace App\Enums;

use App\Services\Availability\OpportunityItemDemandResolver;

/**
 * Which date pair drives a demand's availability window before product buffers
 * are applied (availability-engine.md §"Configurable Availability Window").
 *
 * Read from the `availability.demand_date_source` system setting by the
 * {@see OpportunityItemDemandResolver}.
 */
enum DemandDateSource: string
{
    /**
     * The line item's operational dates (inheriting the opportunity's when
     * null). A prep-to-return model; recommended for most rental companies.
     */
    case Operational = 'operational';

    /**
     * The opportunity's billing window (charge_starts_at / charge_ends_at). A
     * dispatch-to-check-in model.
     */
    case Charge = 'charge';

    public function label(): string
    {
        return match ($this) {
            self::Operational => 'Operational dates',
            self::Charge => 'Charge dates',
        };
    }
}
