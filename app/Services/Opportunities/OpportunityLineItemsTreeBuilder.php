<?php

namespace App\Services\Opportunities;

use App\Data\Availability\OpportunityItemAvailabilityData;
use App\Enums\LineItemTransactionType;
use App\Enums\OpportunityItemType;
use App\Enums\OpportunityState;
use App\Enums\OpportunityStatus;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Services\AvailabilityService;
use App\Support\Formatter;
use Illuminate\Support\Collection;

/**
 * Builds the flat pre-order tree read shape consumed by the local-first line-item
 * editor (Alpine store + frozen seed island).
 */
class OpportunityLineItemsTreeBuilder
{
    public function __construct(
        private readonly Formatter $formatter,
        private readonly AvailabilityService $availability,
        private readonly OpportunityEditorTreeService $treeService,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function tree(Opportunity $opportunity): array
    {
        $opportunity->load([
            'items' => fn ($query) => $query->orderBy('path')->orderBy('id'),
        ]);

        $items = $opportunity->items;
        $paths = $items->pluck('path')->all();
        $groupsByPath = $items
            ->filter(fn (OpportunityItem $item): bool => $item->item_type === OpportunityItemType::Group)
            ->keyBy('path');
        $availability = $this->availabilityMap($opportunity->id);
        $duplicateIds = $this->duplicateLineIds($items);
        $currencyCode = $opportunity->currency_code ?? settings('company.base_currency', 'GBP');
        $showShortageIndicators = $this->shortageIndicatorsVisible($opportunity);

        return $items
            ->map(fn (OpportunityItem $item): array => $this->rowToArray(
                $item,
                $paths,
                $groupsByPath,
                $availability,
                $duplicateIds,
                $opportunity,
                $currencyCode,
                $showShortageIndicators,
            ))
            ->values()
            ->all();
    }

    /**
     * Destination options for the quick-add bar.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function destinations(Opportunity $opportunity): array
    {
        $options = [['value' => '', 'label' => '— Auto group —']];

        $opportunity->load([
            'items' => fn ($query) => $query->orderBy('path')->orderBy('id'),
        ]);

        foreach ($this->treeService->buildDisplayGroups($opportunity->items, fn (): array => []) as $group) {
            $prefix = match ($group['kind']) {
                'group' => 'Section · ',
                'auto' => 'Group · ',
                default => '',
            };

            $options[] = [
                'value' => $group['key'],
                'label' => $prefix.$group['label'],
            ];
        }

        return $options;
    }

    /**
     * Parent-group options for the "New section" modal.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function parentGroupOptions(Opportunity $opportunity): array
    {
        $opportunity->load([
            'items' => fn ($query) => $query->orderBy('path')->orderBy('id'),
        ]);

        return $this->treeService->parentGroupOptions($opportunity->items);
    }

    /**
     * @param  array<int, string>  $paths
     * @param  Collection<string, OpportunityItem>  $groupsByPath
     * @param  array<int, OpportunityItemAvailabilityData>  $availability
     * @param  array<int, bool>  $duplicateIds
     * @return array<string, mixed>
     */
    private function rowToArray(
        OpportunityItem $item,
        array $paths,
        Collection $groupsByPath,
        array $availability,
        array $duplicateIds,
        Opportunity $opportunity,
        string $currencyCode,
        bool $showShortageIndicators,
    ): array {
        $depth = $item->depth();
        $parentPath = $item->parentPath();
        $parentGroupId = null;

        if ($parentPath !== null) {
            $parentGroupId = $groupsByPath->get($parentPath)?->id;
        }

        $productId = $this->catalogueProductId($item);
        $avail = $availability[$item->id] ?? null;
        $statusLabel = $this->availabilityLabel($avail, $showShortageIndicators);
        $days = $this->hireDays($item, $opportunity);
        $chargeBreakdown = $this->chargeBreakdownFor($item, $currencyCode, $days);

        return [
            'id' => (int) $item->id,
            'item_type' => $item->item_type->value,
            'path' => $item->path,
            'depth' => $depth,
            'parent_path' => $parentPath,
            'parent_group_id' => $parentGroupId,
            'revenue_group_id' => $item->revenue_group_id,
            'name' => $item->name,
            'description' => $item->description,
            'notes' => $item->notes,
            'quantity' => $this->formatQuantity($item->quantity),
            'quantity_raw' => (string) $item->quantity,
            'days' => $days,
            'unit_price' => (int) ($item->unit_price ?? 0),
            'unit_price_display' => $this->formatter->money((int) ($item->unit_price ?? 0), $currencyCode),
            'unit_price_raw' => $item->formatMoneyCost('unit_price'),
            'discount_percent' => $item->discount_percent !== null ? (string) $item->discount_percent : null,
            'charge_total' => (int) ($item->total ?? 0),
            'charge_total_display' => $this->formatter->money((int) ($item->total ?? 0), $currencyCode),
            'type_label' => $item->item_type === OpportunityItemType::Group
                ? null
                : ($item->item_type === OpportunityItemType::Text
                    ? OpportunityItemType::Text->label()
                    : $item->transaction_type->label()),
            'status_label' => $statusLabel,
            'availability_status' => $this->availabilityStatus($avail, $showShortageIndicators),
            'has_shortage' => $showShortageIndicators && ($avail !== null && $avail->has_shortage),
            'is_optional' => $item->is_optional,
            'is_collapsed' => false,
            'has_children' => $this->hasChildren($item->path, $paths),
            'product_id' => $productId,
            'product_url' => $productId !== null ? route('products.show', $productId) : null,
            'charge_breakdown' => $chargeBreakdown,
            'availability_url' => $productId !== null ? $this->availabilityUrlFor($opportunity, $productId) : null,
            'starts_at' => optional($item->starts_at)?->toDateString(),
            'ends_at' => optional($item->ends_at)?->toDateString(),
            'charge_period_label' => $item->charge_period->label(),
            'has_duplicates' => $duplicateIds[$item->id] ?? false,
        ];
    }

    /**
     * @param  Collection<int, OpportunityItem>  $items
     * @return array<int, bool>
     */
    private function duplicateLineIds(Collection $items): array
    {
        $byKey = [];

        foreach ($items as $item) {
            if ($item->itemable_id === null || in_array($item->item_type, [OpportunityItemType::Group, OpportunityItemType::Text], true)) {
                continue;
            }

            $key = implode('|', [
                $item->itemable_id,
                $item->itemable_type,
                $item->getRawOriginal('transaction_type'),
                $item->getRawOriginal('charge_period'),
                $item->is_optional ? 1 : 0,
                $item->parentPath() ?? 'null',
                optional($item->starts_at)->toIso8601String() ?? 'null',
                optional($item->ends_at)->toIso8601String() ?? 'null',
            ]);

            $byKey[$key][] = (int) $item->id;
        }

        $flagged = [];

        foreach ($byKey as $ids) {
            if (count($ids) > 1) {
                foreach ($ids as $id) {
                    $flagged[$id] = true;
                }
            }
        }

        return $flagged;
    }

    /**
     * @return array<int, OpportunityItemAvailabilityData>
     */
    private function availabilityMap(int $opportunityId): array
    {
        return $this->availability
            ->getOpportunityContext($opportunityId)
            ->keyBy('opportunity_item_id')
            ->all();
    }

    private function shortageIndicatorsVisible(Opportunity $opportunity): bool
    {
        return $opportunity->state === OpportunityState::Order
            || ($opportunity->state === OpportunityState::Quotation
                && $opportunity->statusEnum() === OpportunityStatus::QuotationReserved);
    }

    private function catalogueProductId(OpportunityItem $item): ?int
    {
        if ($item->itemable_id === null || in_array($item->item_type, [OpportunityItemType::Group, OpportunityItemType::Text], true)) {
            return null;
        }

        if ($item->isProductBacked()) {
            return (int) $item->itemable_id;
        }

        $normalizedType = strtolower((string) $item->itemable_type);

        if ($normalizedType === 'product' || str_ends_with($normalizedType, '\\product')) {
            return (int) $item->itemable_id;
        }

        return null;
    }

    private function availabilityLabel(?OpportunityItemAvailabilityData $avail, bool $showShortageIndicators): ?string
    {
        if ($avail === null) {
            return null;
        }

        if ($avail->has_shortage) {
            return $showShortageIndicators ? 'Shortage' : 'Reserved';
        }

        return $avail->available_for_item > 0 ? 'Available' : 'Reserved';
    }

    private function availabilityStatus(?OpportunityItemAvailabilityData $avail, bool $showShortageIndicators): ?string
    {
        if ($avail === null) {
            return null;
        }

        if ($avail->has_shortage) {
            return $showShortageIndicators ? 'out' : 'reserved';
        }

        return $avail->available_for_item > 0 ? 'available' : 'reserved';
    }

    private function availabilityUrlFor(Opportunity $opportunity, int $productId): string
    {
        $params = array_filter([
            'view' => 'gantt',
            'product' => $productId,
            'store' => $opportunity->store_id,
            'from' => optional($opportunity->starts_at)?->toDateString(),
            'to' => optional($opportunity->ends_at)?->toDateString(),
        ], fn ($value) => $value !== null && $value !== '');

        return route('availability.index', $params);
    }

    private function hireDays(OpportunityItem $item, Opportunity $opportunity): int
    {
        return app(OpportunityItemChargeableDays::class)->forItem($item, $opportunity);
    }

    /**
     * Per-line charge hover breakdown (CRMS-style). Rental amount is the pre-discount
     * gross for the chargeable window; surcharge is reserved for future line surcharges.
     *
     * @return array<string, string>|null
     */
    private function chargeBreakdownFor(
        OpportunityItem $item,
        string $currencyCode,
        int $days,
    ): ?array {
        if ($item->item_type === OpportunityItemType::Group) {
            return null;
        }

        $unitPriceMinor = (int) ($item->unit_price ?? 0);
        $quantity = (float) $item->quantity;

        // Sale lines are a one-off charge — they do NOT multiply by the chargeable
        // days, mirroring OpportunityTotalsCalculator::manualLineSubtotal(). Only
        // Rental/Service lines accrue per day.
        $isSale = $item->transaction_type === LineItemTransactionType::Sale;
        $chargeDays = $isSale ? 1 : max(1, $days);
        $rentalMinor = (int) round($quantity * $unitPriceMinor * $chargeDays);
        $surchargeMinor = 0;

        return [
            'days_line' => sprintf(
                'Days: %s × %d',
                $this->formatter->money($unitPriceMinor, $currencyCode),
                $chargeDays,
            ),
            'rental_charge_display' => $this->formatter->money($rentalMinor, $currencyCode),
            'surcharge_display' => $this->formatter->money($surchargeMinor, $currencyCode),
        ];
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function hasChildren(string $path, array $paths): bool
    {
        foreach ($paths as $candidate) {
            if ($candidate !== $path && str_starts_with($candidate, $path)) {
                return true;
            }
        }

        return false;
    }

    private function formatQuantity(float|string $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.') ?: '0';
    }
}
