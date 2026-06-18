<?php

namespace App\Services\Opportunities;

use App\Enums\LineItemTransactionType;
use App\Enums\OpportunityCostType;
use App\Enums\RateTransactionType;
use App\Models\Opportunity;
use App\Models\OpportunityCost;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\ProductTaxClass;
use App\Services\RateEngine\RateCalculator;
use App\Services\RateEngine\RateResolver;
use App\Services\TaxCalculator;
use App\ValueObjects\CalculationContext;
use Illuminate\Support\Carbon;

/**
 * Derives the priced totals for opportunity line items and rolls them up onto the
 * parent opportunity, using the shared rate + tax engines.
 *
 * A line's NET (tax-exclusive) total is resolved as:
 *
 *   1. resolve a per-unit price — a non-null manual override always wins; else
 *      the matched {@see ProductRate} price; else 0 when nothing matches.
 *   2. derive the subtotal — for a rate-backed line the {@see RateCalculator}
 *      produces the duration-aware subtotal (via the rate definition's strategy,
 *      base period and modifiers); for a manual/no-rate line the subtotal is
 *      `unit_price * round(quantity)`.
 *   3. apply the line discount (HALF_UP at the minor-unit boundary, BEFORE tax).
 *
 * The discounted net is stored on `opportunity_items.total`. Tax is computed
 * per-line (RMS line-level rounding) and summed at the opportunity level — never
 * on the aggregate — so the projected `tax_total` matches an invoice's.
 *
 * Both methods are idempotent (they overwrite), so they are safe to run inside an
 * event handle() and reproduce identical totals on replay. They write projection
 * rows quietly (updateQuietly / saveQuietly) so no model events fire and no
 * further Verbs events are triggered.
 */
class OpportunityTotalsCalculator
{
    public function __construct(
        private readonly RateResolver $rateResolver,
        private readonly RateCalculator $rateCalculator,
        private readonly TaxCalculator $taxCalculator,
    ) {}

    /**
     * Recompute and persist the priced fields (`unit_price`, `total`, `tax_rate`)
     * for a single line item. Does NOT touch the parent — call {@see rollUp()}
     * afterwards.
     *
     * `$manualUnitPrice` is the event-sourced manual override (from
     * {@see OpportunityItemState::$manual_unit_price}). When non-null it always
     * wins over the resolved rate; null defers to the rate engine. Passing it
     * explicitly keeps the override authoritative and replay-stable (the price-
     * overriding event carries it in its payload).
     */
    public function recalculateItem(OpportunityItem $item, ?int $manualUnitPrice = null): void
    {
        $opportunity = $item->opportunity()->first();

        if ($opportunity === null) {
            return;
        }

        $currency = $opportunity->currency_code ?? 'GBP';
        $quantityUnits = max(0, (int) round((float) $item->quantity));

        [$start, $end] = $this->effectiveDates($item, $opportunity);

        $product = $this->resolveProduct($item);

        [$unitPrice, $netSubtotal] = $this->resolveSubtotal(
            $item,
            $product,
            $currency,
            $quantityUnits,
            $start,
            $end,
            $opportunity->store_id,
            $manualUnitPrice,
        );

        $discountedNet = $this->applyDiscount($netSubtotal, $item->discount_percent);

        $taxResult = $opportunity->prices_include_tax
            ? $this->taxCalculator->calculateInclusive(
                $discountedNet,
                $currency,
                $opportunity->member?->sale_tax_class_id,
                $product?->tax_class_id,
            )
            : $this->taxCalculator->calculate(
                $discountedNet,
                $currency,
                $opportunity->member?->sale_tax_class_id,
                $product?->tax_class_id,
            );

        $item->forceFill([
            'unit_price' => $unitPrice,
            'total' => $discountedNet,
            'tax_rate' => $taxResult->ratePercentage,
            'currency_code' => $currency,
        ])->saveQuietly();
    }

    /**
     * Recompute and persist the resolved `tax_rate` for a single cost. Does NOT
     * touch the parent — call {@see rollUp()} afterwards.
     *
     * Costs are NOT priced by the rate engine — the operator-supplied `amount`
     * stands as the per-unit charge. Only the tax rate is resolved here (against
     * the opportunity member's tax class + the default product tax class, since a
     * cost has no product), so the projected `tax_rate` matches what the rollup
     * applies and an invoice would show.
     */
    public function recalculateCost(OpportunityCost $cost): void
    {
        $opportunity = $cost->opportunity()->first();

        if ($opportunity === null) {
            return;
        }

        $currency = $opportunity->currency_code ?? 'GBP';

        $taxResult = $opportunity->prices_include_tax
            ? $this->taxCalculator->calculateInclusive(
                $this->costLineTotal($cost),
                $currency,
                $opportunity->member?->sale_tax_class_id,
                $this->defaultProductTaxClassId(),
            )
            : $this->taxCalculator->calculate(
                $this->costLineTotal($cost),
                $currency,
                $opportunity->member?->sale_tax_class_id,
                $this->defaultProductTaxClassId(),
            );

        $cost->forceFill([
            'tax_rate' => $taxResult->ratePercentage,
            'currency_code' => $currency,
        ])->saveQuietly();
    }

    /**
     * Reload the opportunity's non-removed items AND costs, sum the per-type charge
     * totals (optional lines/costs excluded), compute line-level tax, and persist
     * all the RMS charge totals onto the projection row.
     *
     * Items are priced by the rate + tax engines (their net is read from the stored
     * `opportunity_items.total`); costs carry their own `amount` and are taxed but
     * never rate-priced. Cost net is routed by cost type into the transit /
     * loss-damage / service buckets, and ALL non-optional costs (and items) feed
     * the tax-exclusive / tax / tax-inclusive / charge headline totals.
     */
    public function rollUp(Opportunity $opportunity): void
    {
        $items = $opportunity->items()->get();
        $costs = $opportunity->costs()->get();

        $rental = 0;
        $sale = 0;
        $service = 0;
        $transit = 0;
        $lossDamage = 0;
        $excludingTax = 0;
        $taxTotal = 0;
        $includingTax = 0;

        $pricesIncludeTax = $opportunity->prices_include_tax;
        $currency = $opportunity->currency_code ?? 'GBP';
        $orgTaxClassId = $opportunity->member?->sale_tax_class_id;

        foreach ($items as $item) {
            if ($item->is_optional) {
                continue;
            }

            $product = $this->resolveProduct($item);
            $lineTotal = (int) $item->total;

            $taxResult = $pricesIncludeTax
                ? $this->taxCalculator->calculateInclusive($lineTotal, $currency, $orgTaxClassId, $product?->tax_class_id)
                : $this->taxCalculator->calculate($lineTotal, $currency, $orgTaxClassId, $product?->tax_class_id);

            // In inclusive mode the stored line total is the GROSS; net is the
            // extracted component. In exclusive mode the stored total is the net.
            $lineNet = $pricesIncludeTax ? $taxResult->netAmount : $lineTotal;
            $lineGross = $pricesIncludeTax ? $lineTotal : $taxResult->grossAmount;

            $excludingTax += $lineNet;
            $taxTotal += $taxResult->taxAmount;
            $includingTax += $lineGross;

            match ($item->transaction_type) {
                LineItemTransactionType::Sale => $sale += $lineNet,
                LineItemTransactionType::Service => $service += $lineNet,
                default => $rental += $lineNet, // Rental + SubRental
            };
        }

        $defaultProductTaxClassId = $this->defaultProductTaxClassId();

        foreach ($costs as $cost) {
            if ($cost->is_optional) {
                continue;
            }

            $costTotal = $this->costLineTotal($cost);

            $taxResult = $pricesIncludeTax
                ? $this->taxCalculator->calculateInclusive($costTotal, $currency, $orgTaxClassId, $defaultProductTaxClassId)
                : $this->taxCalculator->calculate($costTotal, $currency, $orgTaxClassId, $defaultProductTaxClassId);

            $costNet = $pricesIncludeTax ? $taxResult->netAmount : $costTotal;
            $costGross = $pricesIncludeTax ? $costTotal : $taxResult->grossAmount;

            $excludingTax += $costNet;
            $taxTotal += $taxResult->taxAmount;
            $includingTax += $costGross;

            // Route the cost net into the RMS category bucket; delivery feeds the
            // transit total, loss/damage feeds its own total, everything else the
            // general service total.
            match ($cost->cost_type) {
                OpportunityCostType::Delivery => $transit += $costNet,
                OpportunityCostType::LossDamage => $lossDamage += $costNet,
                default => $service += $costNet,
            };
        }

        // A manual deal-total override replaces the computed headline; otherwise
        // the headline is the gross (tax-inclusive) total.
        $chargeTotal = $opportunity->deal_total ?? $includingTax;

        $opportunity->forceFill([
            'rental_charge_total' => $rental,
            'sale_charge_total' => $sale,
            'service_charge_total' => $service,
            // Sub-hire is a Phase 4 deliverable — no source populates this yet.
            'sub_rental_charge_total' => 0,
            'transit_charge_total' => $transit,
            'loss_damage_charge_total' => $lossDamage,
            'charge_excluding_tax_total' => $excludingTax,
            'tax_total' => $taxTotal,
            'charge_including_tax_total' => $includingTax,
            'charge_total' => $chargeTotal,
        ])->saveQuietly();
    }

    /**
     * The cost's stored line value in minor units: amount * round(quantity). In
     * inclusive mode this is the gross; in exclusive mode the net — mirroring the
     * line-item `total` convention.
     */
    private function costLineTotal(OpportunityCost $cost): int
    {
        $quantityUnits = max(0, (int) round((float) $cost->quantity));

        return $cost->amount * $quantityUnits;
    }

    /**
     * Resolve the id of the default product tax class (costs have no product, so
     * they tax against the default class). Null when no default is configured,
     * which yields zero-tax — the same outcome as an untaxed line.
     */
    private function defaultProductTaxClassId(): ?int
    {
        return ProductTaxClass::query()->where('is_default', true)->value('id');
    }

    /**
     * Resolve the per-unit price and the NET subtotal (pre-discount) for a line.
     *
     * @return array{0: int, 1: int} [unitPrice, netSubtotal] in minor units
     */
    private function resolveSubtotal(
        OpportunityItem $item,
        ?Product $product,
        string $currency,
        int $quantityUnits,
        Carbon $start,
        Carbon $end,
        ?int $storeId,
        ?int $manualUnitPrice,
    ): array {
        // A manual override always wins over the rate engine.
        if ($manualUnitPrice !== null) {
            return [$manualUnitPrice, $manualUnitPrice * $quantityUnits];
        }

        $rate = $product !== null
            ? $this->rateResolver->resolve($product, $this->mapTransactionType($item->transaction_type), $storeId, $start)
            : null;

        if ($rate === null || $rate->rateDefinition === null) {
            // No rate matched: fall back to whatever unit price is on the line
            // (0 for a fresh ad-hoc line) times the quantity.
            $unitPrice = $item->unit_price;

            return [$unitPrice, $unitPrice * $quantityUnits];
        }

        $definition = $rate->rateDefinition;

        $context = new CalculationContext(
            unitPriceMinor: $rate->price,
            currency: $currency,
            start: $start,
            end: $end,
            quantity: max(1, $quantityUnits),
            basePeriod: $definition->base_period,
            strategyConfig: $definition->strategy_config,
            transactionType: $this->mapTransactionType($item->transaction_type),
            storeId: $storeId,
        );

        $breakdown = $this->rateCalculator->calculate(
            $context,
            $definition->calculation_strategy->value,
            $definition->enabled_modifiers,
            $definition->modifier_configs,
        );

        return [$rate->price, $breakdown->totalMinor()];
    }

    /**
     * Apply a percentage discount to a net amount, rounding HALF_UP at the
     * minor-unit boundary. A null/zero discount returns the amount unchanged.
     */
    private function applyDiscount(int $netMinor, ?string $discountPercent): int
    {
        if ($discountPercent === null || bccomp($discountPercent, '0', 10) === 0) {
            return $netMinor;
        }

        // discount = round(net * percent / 100), HALF_UP, on the minor-unit grid.
        $rawDiscount = bcdiv(bcmul((string) $netMinor, $discountPercent, 10), '100', 10);
        $discount = (int) bcadd($rawDiscount, str_starts_with($rawDiscount, '-') ? '-0.5' : '0.5', 0);

        return $netMinor - $discount;
    }

    /**
     * Resolve the line's effective rental window, inheriting the opportunity's
     * dates when the item has none, falling back to now / now+ when both are null.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function effectiveDates(OpportunityItem $item, Opportunity $opportunity): array
    {
        $start = $item->starts_at ?? $opportunity->starts_at ?? Carbon::now();
        $end = $item->ends_at ?? $opportunity->ends_at ?? Carbon::parse($start)->copy()->addDay();

        return [Carbon::parse($start), Carbon::parse($end)];
    }

    private function resolveProduct(OpportunityItem $item): ?Product
    {
        if ($item->item_id === null || ! $this->referencesProduct($item->item_type)) {
            return null;
        }

        return Product::query()->find($item->item_id);
    }

    private function referencesProduct(?string $type): bool
    {
        if ($type === null) {
            return false;
        }

        return $type === Product::class || strtolower($type) === 'product';
    }

    private function mapTransactionType(LineItemTransactionType $type): RateTransactionType
    {
        return match ($type) {
            LineItemTransactionType::Sale => RateTransactionType::Sale,
            LineItemTransactionType::Service => RateTransactionType::Service,
            default => RateTransactionType::Rental, // Rental + SubRental
        };
    }
}
