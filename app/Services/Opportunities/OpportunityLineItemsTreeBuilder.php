<?php

namespace App\Services\Opportunities;

use App\Data\Availability\OpportunityItemAvailabilityData;
use App\Enums\OpportunityItemType;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Services\AvailabilityService;
use App\Support\Formatter;
use Illuminate\Support\Carbon;
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

        return $items
            ->map(fn (OpportunityItem $item): array => $this->rowToArray(
                $item,
                $paths,
                $groupsByPath,
                $availability,
                $duplicateIds,
                $opportunity,
                $currencyCode,
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
    ): array {
        $depth = $item->depth();
        $parentPath = $item->parentPath();
        $parentGroupId = null;

        if ($parentPath !== null) {
            $parentGroupId = $groupsByPath->get($parentPath)?->id;
        }

        $productId = $item->isProductBacked() ? $item->itemable_id : null;
        $avail = $availability[$item->id] ?? null;
        $statusLabel = $this->availabilityLabel($avail);

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
            'quantity' => $this->formatQuantity($item->quantity),
            'quantity_raw' => (string) $item->quantity,
            'days' => $this->hireDays($item),
            'unit_price' => (int) ($item->unit_price ?? 0),
            'unit_price_display' => $this->formatter->money((int) ($item->unit_price ?? 0), $currencyCode),
            'unit_price_raw' => $item->formatMoneyCost('unit_price'),
            'discount_percent' => $item->discount_percent !== null ? (string) $item->discount_percent : null,
            'charge_total' => (int) ($item->total ?? 0),
            'charge_total_display' => $this->formatter->money((int) ($item->total ?? 0), $currencyCode),
            'type_label' => $item->item_type === OpportunityItemType::Group
                ? null
                : $item->transaction_type->label(),
            'status_label' => $statusLabel,
            'availability_status' => $this->availabilityStatus($avail),
            'has_shortage' => $avail !== null ? $avail->has_shortage : false,
            'is_optional' => $item->is_optional,
            'is_collapsed' => false,
            'has_children' => $this->hasChildren($item->path, $paths),
            'product_id' => $productId,
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
            if ($item->itemable_id === null || $item->item_type === OpportunityItemType::Group) {
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

    private function availabilityLabel(?OpportunityItemAvailabilityData $avail): ?string
    {
        if ($avail === null) {
            return null;
        }

        if ($avail->has_shortage) {
            return 'Shortage';
        }

        return $avail->available_for_item > 0 ? 'Available' : 'Reserved';
    }

    private function availabilityStatus(?OpportunityItemAvailabilityData $avail): ?string
    {
        if ($avail === null) {
            return null;
        }

        if ($avail->has_shortage) {
            return 'out';
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

    private function hireDays(OpportunityItem $item): int
    {
        if ($item->starts_at === null || $item->ends_at === null) {
            return 1;
        }

        $start = Carbon::parse($item->starts_at);
        $end = Carbon::parse($item->ends_at);

        return max(1, (int) $start->diffInDays($end));
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
