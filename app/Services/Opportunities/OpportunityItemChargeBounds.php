<?php

namespace App\Services\Opportunities;

use App\Models\Opportunity;
use App\Models\OpportunityItem;
use Illuminate\Validation\ValidationException;

/**
 * Guards opportunity line-item money columns stored as signed 32-bit integers.
 */
final class OpportunityItemChargeBounds
{
    public const MAX_MINOR = 2_147_483_647;

    public function assertUnitPriceAndProjectedTotalFit(
        OpportunityItem $item,
        Opportunity $opportunity,
        ?int $unitPriceMinor,
    ): void {
        if ($unitPriceMinor === null) {
            return;
        }

        if ($unitPriceMinor < 0 || $unitPriceMinor > self::MAX_MINOR) {
            throw ValidationException::withMessages([
                'unit_price' => 'The unit price exceeds the maximum allowed value.',
            ]);
        }

        $projectedTotal = $this->projectedChargeTotalMinor($item, $opportunity, $unitPriceMinor);

        if ($projectedTotal > self::MAX_MINOR) {
            throw ValidationException::withMessages([
                'unit_price' => 'The resulting line charge total exceeds the maximum allowed value for this quantity and hire period.',
            ]);
        }
    }

    public function projectedChargeTotalMinor(
        OpportunityItem $item,
        Opportunity $opportunity,
        int $unitPriceMinor,
    ): int {
        $quantity = (float) $item->quantity;
        $days = max(1, app(OpportunityItemChargeableDays::class)->forItem($item, $opportunity));
        $gross = $quantity * $unitPriceMinor * $days;
        $discount = $item->discount_percent !== null ? (float) $item->discount_percent : 0.0;

        return (int) round($gross * (1 - ($discount / 100)));
    }
}
