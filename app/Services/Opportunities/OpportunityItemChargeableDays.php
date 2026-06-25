<?php

namespace App\Services\Opportunities;

use App\Models\Opportunity;
use App\Models\OpportunityItem;
use Illuminate\Support\Carbon;

/**
 * Single source for the chargeable-day count shown in the line-items editor
 * "days" column and applied to manual/no-rate rental pricing.
 */
class OpportunityItemChargeableDays
{
    public function forItem(OpportunityItem $item, ?Opportunity $opportunity = null): int
    {
        $opportunity ??= $item->relationLoaded('opportunity')
            ? $item->opportunity
            : $item->opportunity()->first();

        if ($opportunity !== null && $opportunity->use_chargeable_days && $opportunity->chargeable_days !== null) {
            return max(1, (int) round((float) $opportunity->chargeable_days));
        }

        $start = $item->starts_at ?? $opportunity?->starts_at;
        $end = $item->ends_at ?? $opportunity?->ends_at;

        if ($start === null || $end === null) {
            return 1;
        }

        return max(1, (int) Carbon::parse($start)->diffInDays(Carbon::parse($end)));
    }
}
