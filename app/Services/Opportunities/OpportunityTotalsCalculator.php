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
 * TAX MODEL (Ben's directive — "all totals ex-tax, taxes calculated finally"):
 * every stored money total is NET (tax-exclusive). Tax is NEVER applied per-line
 * during the build; it is computed in a single FINAL pass at rollup time by
 * grouping the net amounts by their effective tax class and applying each group's
 * rate once. The only with-tax figure stored is `charge_including_tax_total`,
 * derived at the very end as `charge_total (net) + tax_total`.
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
 *   4. NET EXTRACTION when `prices_include_tax`: an input entered tax-inclusive has
 *      its embedded tax stripped at this point ({@see TaxCalculator::calculateInclusive})
 *      so the stored `opportunity_items.total` is always NET regardless of input
 *      mode. The final rollup tax pass then re-derives tax additively on the net.
 *
 * The discounted NET is stored on `opportunity_items.total`. The line's resolved
 * `tax_rate` is recorded for display/reporting only — it is NOT used to store a
 * taxed line total.
 *
 * GROUPED FINAL TAX: {@see rollUp()} buckets every non-optional line's and cost's
 * net by its effective tax class — the opportunity member/organisation sale tax
 * class combined with the item's product tax class (or the default product tax
 * class for costs, which have no product). Each group's net is taxed once via
 * {@see TaxCalculator::calculate()} and the results summed. This keeps a
 * mixed-tax-class basket correct (each class rounds at its own boundary, matching
 * an invoice) while remaining a SINGLE final calculation, not per-line-during-build.
 * When an item has no resolvable product tax class it falls back to the default
 * product tax class — the same fallback {@see TaxCalculator::resolveRule()} applies.
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
     * for a single line item. The stored `total` is always NET. Does NOT touch the
     * parent — call {@see rollUp()} afterwards.
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

        $discounted = $this->applyDiscount($netSubtotal, $item->discount_percent);

        // When prices are entered tax-inclusive, strip the embedded tax so the
        // STORED total is net; the final rollup tax pass re-derives tax additively.
        $orgTaxClassId = $opportunity->member?->sale_tax_class_id;
        $net = $opportunity->prices_include_tax
            ? $this->taxCalculator->calculateInclusive($discounted, $currency, $orgTaxClassId, $product?->tax_class_id)->netAmount
            : $discounted;

        // The line tax_rate is recorded for display/reporting only.
        $taxRate = $this->taxCalculator
            ->calculate($net, $currency, $orgTaxClassId, $product?->tax_class_id)
            ->ratePercentage;

        $item->forceFill([
            'unit_price' => $unitPrice,
            'total' => $net,
            'tax_rate' => $taxRate,
            'currency_code' => $currency,
        ])->saveQuietly();
    }

    /**
     * Recompute and persist the resolved `tax_rate` for a single cost. Does NOT
     * touch the parent — call {@see rollUp()} afterwards.
     *
     * Costs are NOT priced by the rate engine — the operator-supplied `amount`
     * stands as the per-unit charge. Only the display `tax_rate` is resolved here
     * (against the opportunity member's tax class + the default product tax class,
     * since a cost has no product). The stored `amount` is the per-unit NET; the
     * net rollup bucket is `amount * quantity` (with inclusive extraction applied
     * at rollup time if `prices_include_tax`).
     */
    public function recalculateCost(OpportunityCost $cost): void
    {
        $opportunity = $cost->opportunity()->first();

        if ($opportunity === null) {
            return;
        }

        $currency = $opportunity->currency_code ?? 'GBP';

        $taxRate = $this->taxCalculator->calculate(
            $this->costNet($cost, $opportunity),
            $currency,
            $opportunity->member?->sale_tax_class_id,
            $this->defaultProductTaxClassId(),
        )->ratePercentage;

        $cost->forceFill([
            'tax_rate' => $taxRate,
            'currency_code' => $currency,
        ])->saveQuietly();
    }

    /**
     * Reload the opportunity's non-removed items AND costs, sum the per-type NET
     * charge totals (optional lines/costs excluded), compute the tax in a single
     * GROUPED FINAL pass, and persist all the RMS charge totals onto the
     * projection row.
     *
     * All per-type and headline totals are NET. The net amounts are bucketed by
     * effective tax class while they are summed; once every net total is known, the
     * tax for each group is computed once and the results summed into `tax_total`.
     * `charge_including_tax_total` is then `charge_total (net) + tax_total`.
     *
     * Items are priced by the rate engine (their NET is read from the stored
     * `opportunity_items.total`); costs carry their own NET `amount`. Cost net is
     * routed by cost type into the transit / loss-damage / service buckets.
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

        $orgTaxClassId = $opportunity->member?->sale_tax_class_id;

        /**
         * Net amounts bucketed by effective tax class, keyed by product tax class
         * id (org class is constant for the opportunity). `0` = no resolvable
         * product class (taxes via the default class fallback).
         *
         * @var array<int, int> $netByProductTaxClass
         */
        $netByProductTaxClass = [];

        foreach ($items as $item) {
            if ($item->is_optional) {
                continue;
            }

            $product = $this->resolveProduct($item);
            $lineNet = (int) $item->total;

            $excludingTax += $lineNet;
            $taxClassKey = $product !== null ? (int) $product->tax_class_id : 0;
            $netByProductTaxClass[$taxClassKey] = ($netByProductTaxClass[$taxClassKey] ?? 0) + $lineNet;

            match ($item->transaction_type) {
                LineItemTransactionType::Sale => $sale += $lineNet,
                LineItemTransactionType::Service => $service += $lineNet,
                // Rental only — SubRental is rejected at the input boundary
                // ({@see AddOpportunityItemData}), so no line ever reaches this
                // branch as SubRental, keeping the `sub_rental_charge_total => 0`
                // stub below correct until the Phase 4 sub-hire path exists.
                default => $rental += $lineNet,
            };
        }

        $defaultProductTaxClassId = $this->defaultProductTaxClassId();

        foreach ($costs as $cost) {
            if ($cost->is_optional) {
                continue;
            }

            $costNet = $this->costNet($cost, $opportunity);

            $excludingTax += $costNet;
            $netByProductTaxClass[$defaultProductTaxClassId ?? 0]
                = ($netByProductTaxClass[$defaultProductTaxClassId ?? 0] ?? 0) + $costNet;

            // Route the cost net into the RMS category bucket; delivery feeds the
            // transit total, loss/damage feeds its own total, everything else the
            // general service total.
            match ($cost->cost_type) {
                OpportunityCostType::Delivery => $transit += $costNet,
                OpportunityCostType::LossDamage => $lossDamage += $costNet,
                default => $service += $costNet,
            };
        }

        // A deal-total override is a NET override of the headline (Ben: the deal
        // price is net of tax). The per-type component totals keep reflecting the
        // real lines, but the net headline + final tax are computed on the deal
        // using the opportunity's blended tax context (org class + default product
        // class).
        if ($opportunity->deal_total !== null) {
            $netHeadline = (int) $opportunity->deal_total;
            $taxTotal = $this->taxCalculator->calculate(
                $netHeadline,
                $opportunity->currency_code ?? 'GBP',
                $orgTaxClassId,
                $defaultProductTaxClassId,
            )->taxAmount;
        } else {
            $netHeadline = $excludingTax;
            $taxTotal = $this->groupedTaxTotal(
                $netByProductTaxClass,
                $opportunity->currency_code ?? 'GBP',
                $orgTaxClassId,
            );
        }

        $opportunity->forceFill([
            'rental_charge_total' => $rental,
            'sale_charge_total' => $sale,
            'service_charge_total' => $service,
            // Sub-hire is a Phase 4 deliverable — no source populates this yet, and
            // SubRental line items are rejected at the input boundary, so 0 is safe.
            'sub_rental_charge_total' => 0,
            'transit_charge_total' => $transit,
            'loss_damage_charge_total' => $lossDamage,
            // All headline totals are NET. charge_total == charge_excluding_tax_total.
            'charge_excluding_tax_total' => $netHeadline,
            'tax_total' => $taxTotal,
            'charge_including_tax_total' => $netHeadline + $taxTotal,
            'charge_total' => $netHeadline,
        ])->saveQuietly();
    }

    /**
     * Compute the total tax across all tax-class groups in a single final pass.
     *
     * Each group's net is taxed once via {@see TaxCalculator::calculate()} (which
     * rounds at the currency minor-unit boundary per group, matching invoice
     * line-level rounding for mixed-class baskets) and the per-group tax summed.
     *
     * @param  array<int, int>  $netByProductTaxClass  net minor units keyed by product tax class id (0 = default fallback)
     */
    private function groupedTaxTotal(array $netByProductTaxClass, string $currency, ?int $orgTaxClassId): int
    {
        $taxTotal = 0;

        foreach ($netByProductTaxClass as $productTaxClassId => $groupNet) {
            $taxTotal += $this->taxCalculator->calculate(
                $groupNet,
                $currency,
                $orgTaxClassId,
                $productTaxClassId === 0 ? null : $productTaxClassId,
            )->taxAmount;
        }

        return $taxTotal;
    }

    /**
     * The cost's NET line value in minor units: amount * round(quantity), with the
     * embedded tax stripped when prices are entered tax-inclusive so the bucket
     * stays net.
     */
    private function costNet(OpportunityCost $cost, Opportunity $opportunity): int
    {
        $quantityUnits = max(0, (int) round((float) $cost->quantity));
        $lineTotal = $cost->amount * $quantityUnits;

        if (! $opportunity->prices_include_tax) {
            return $lineTotal;
        }

        return $this->taxCalculator->calculateInclusive(
            $lineTotal,
            $opportunity->currency_code ?? 'GBP',
            $opportunity->member?->sale_tax_class_id,
            $this->defaultProductTaxClassId(),
        )->netAmount;
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
     * dates when the item has none.
     *
     * NOTE: both the item and the opportunity dates are baked into the firing
     * event at fire-time (the action resolves item ?? opportunity ?? a single
     * captured now() and passes concrete dates into the ItemAdded / ItemDatesChanged
     * payload). This method therefore never needs a now() fallback — a dateless
     * rate-priced line already carries a concrete window on its projection row, so
     * replay reproduces identical totals. A missing date is therefore an invariant
     * violation, surfaced as a LogicException rather than silently substituting a
     * non-deterministic now() (which would make the total irreproducible on replay).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function effectiveDates(OpportunityItem $item, Opportunity $opportunity): array
    {
        $start = $item->starts_at ?? $opportunity->starts_at;
        $end = $item->ends_at ?? $opportunity->ends_at;

        if ($start === null || $end === null) {
            throw new \LogicException(sprintf(
                'Opportunity item %s has no resolvable hire window: dates are baked into the firing event '.
                'at fire-time, so a dateless projection row is an invariant violation that would make the '.
                'total non-deterministic on replay.',
                $item->getKey() ?? '(unsaved)',
            ));
        }

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
