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
        // Mirror OpportunityTotalsCalculator::manualLineSubtotal exactly so the
        // guard never permits a value the calculator then overflows: the quantity is
        // rounded to whole units (not the raw fractional value) and the discount is
        // applied with the same bcmath HALF_UP rounding on the minor-unit grid.
        $quantityUnits = max(0, (int) round((float) $item->quantity));
        $days = max(1, app(OpportunityItemChargeableDays::class)->forItem($item, $opportunity));

        $gross = $unitPriceMinor * $quantityUnits * $days;

        return $this->applyDiscount($gross, $item->discount_percent);
    }

    /**
     * Apply a percentage discount to a net minor amount, rounding HALF_UP at the
     * minor-unit boundary — identical to
     * {@see OpportunityTotalsCalculator::applyDiscount()}.
     */
    private function applyDiscount(int $netMinor, ?string $discountPercent): int
    {
        if ($discountPercent === null || bccomp($discountPercent, '0', 10) === 0) {
            return $netMinor;
        }

        $rawDiscount = bcdiv(bcmul((string) $netMinor, $discountPercent, 10), '100', 10);
        $discount = (int) bcadd($rawDiscount, str_starts_with($rawDiscount, '-') ? '-0.5' : '0.5', 0);

        return $netMinor - $discount;
    }
}
