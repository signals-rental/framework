<?php

namespace App\Actions\Opportunities;

use App\Actions\Opportunities\Concerns\FormatsOpportunityDates;
use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\OpportunityData;
use App\Enums\OpportunityItemType;
use App\Models\Accessory;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Services\Opportunities\ItemTreeService;
use App\Services\Opportunities\OpportunityAutoGroupResolver;
use App\Services\SequenceAllocator;
use App\Services\Shortages\ItemShortageProbe;
use App\Verbs\Events\Opportunities\ItemAdded;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Adds a line item to an opportunity via the ItemAdded genesis event, allocating
 * the replay-stable item id, firing the event, and committing it with its
 * projection atomically.
 *
 * After the event commits, the {@see ItemShortageProbe} runs a cheap inline
 * shortage check on the new line (shortage-resolution-sub-hires.md §2.4),
 * emitting `shortage.detected` when the line is short. The probe never blocks the
 * add and is skipped during replay.
 */
class AddOpportunityItem
{
    use CommitsVerbsEvents, FormatsOpportunityDates;

    public function __invoke(Opportunity $opportunity, AddOpportunityItemData $data): OpportunityData
    {
        Gate::authorize('opportunities.edit');

        // Resolve the effective hire window NOW (item dates ?? opportunity dates ??
        // a single captured fire-time now()) and bake CONCRETE dates into the event
        // so the totals engine never calls now() in handle(): a dateless rate-priced
        // line carries a concrete window on its projection row, keeping replay
        // totals identical. See OpportunityTotalsCalculator::effectiveDates().
        $now = Carbon::now('UTC');
        $startsAt = $data->starts_at
            ?? $this->toIso($opportunity->starts_at)
            ?? $now->toIso8601String();
        $endsAt = $data->ends_at
            ?? $this->toIso($opportunity->ends_at)
            ?? Carbon::parse($startsAt)->copy()->addDay()->toIso8601String();

        $itemId = null;

        // When the opportunity carries an active quote version, the new line lands
        // in that version's scope; a non-versioned opportunity keeps a NULL
        // version_id (unchanged behaviour). A null override on the data wins (used
        // by VersionCreated to target a specific brand-new version).
        $versionId = $data->version_id ?? ($opportunity->active_version_id > 0 ? $opportunity->active_version_id : null);

        $this->commitVerbs(function () use ($opportunity, $data, $startsAt, $endsAt, $versionId, &$itemId): void {
            // Allocate the replay-stable small PK and bake it into the event so a
            // truncate + Verbs::replay() rebuild reproduces the identical id.
            $itemId = app(SequenceAllocator::class)->next('opportunity_items');

            // Resolve the tree position. A line with a parent_path nests one level
            // deeper under that group/product; otherwise it lands at the top level.
            // The path is allocated inside the closure (after $itemId) and baked into
            // the event, so a Verbs replay reproduces the identical position — the
            // same replay-stability rationale the old sort_order carried.
            $tree = app(ItemTreeService::class);
            $path = $data->parent_path !== null
                ? $tree->nextChildPath($opportunity->id, $versionId, $data->parent_path)
                : $tree->nextTopLevelPath($opportunity->id, $versionId);

            $revenueGroupId = $data->revenue_group_id ?? $this->resolveRevenueGroupId($data);

            ItemAdded::fire(
                opportunity_item_id: $itemId,
                opportunity_id: $opportunity->id,
                version_id: $versionId,
                itemable_id: $data->itemable_id,
                itemable_type: $data->itemable_type,
                item_type: $data->item_type,
                path: $path,
                revenue_group_id: $revenueGroupId,
                name: $data->name,
                description: $data->description,
                quantity: $data->quantity,
                transaction_type: $data->transaction_type,
                charge_period: $data->charge_period,
                starts_at: $startsAt,
                ends_at: $endsAt,
                is_optional: $data->is_optional,
                manual_unit_price: $data->unit_price,
                discount_percent: $data->discount_percent,
                custom_fields: $data->custom_fields,
                notes: $data->notes,
            );

            if ($this->shouldMaterializeIncludedAccessories($data)) {
                $this->materializeIncludedAccessories(
                    opportunity: $opportunity,
                    versionId: $versionId,
                    principalPath: $path,
                    productId: (int) $data->itemable_id,
                    productQuantity: $data->quantity,
                    transactionType: $data->transaction_type,
                    chargePeriod: $data->charge_period,
                    startsAt: $startsAt,
                    endsAt: $endsAt,
                    isOptional: $data->is_optional,
                );
            }
        });

        $fresh = $opportunity->fresh(['items']);

        // Inline shortage detection (§2.4) on the newly-added line. Cheap, never
        // blocks the add, replay-guarded inside the probe.
        if ($itemId !== null && $fresh !== null) {
            $item = $fresh->items->firstWhere('id', $itemId);

            if ($item !== null) {
                app(ItemShortageProbe::class)->probe($item);
            }
        }

        return OpportunityData::fromModel($fresh ?? $opportunity);
    }

    private function shouldMaterializeIncludedAccessories(AddOpportunityItemData $data): bool
    {
        if (! $data->materialize_included_accessories) {
            return false;
        }

        return $data->item_type === OpportunityItemType::Product->value
            && $data->itemable_id !== null
            && $data->itemable_type === Product::class;
    }

    private function resolveRevenueGroupId(AddOpportunityItemData $data): ?int
    {
        if ($data->itemable_id === null || $data->itemable_type !== Product::class) {
            return null;
        }

        $stub = new OpportunityItem([
            'itemable_id' => $data->itemable_id,
            'itemable_type' => $data->itemable_type,
            'transaction_type' => $data->transaction_type,
        ]);

        [$revenueGroupId] = app(OpportunityAutoGroupResolver::class)->resolve($stub);

        return $revenueGroupId;
    }

    private function materializeIncludedAccessories(
        Opportunity $opportunity,
        ?int $versionId,
        string $principalPath,
        int $productId,
        string $productQuantity,
        int $transactionType,
        int $chargePeriod,
        string $startsAt,
        string $endsAt,
        bool $isOptional,
    ): void {
        $accessories = Accessory::query()
            ->where('product_id', $productId)
            ->where('included', true)
            ->with('accessoryProduct')
            ->orderBy('sort_order')
            ->get();

        if ($accessories->isEmpty()) {
            return;
        }

        $tree = app(ItemTreeService::class);

        foreach ($accessories as $accessory) {
            $accessoryProduct = $accessory->accessoryProduct;

            if ($accessoryProduct === null) {
                continue;
            }

            $accessoryId = app(SequenceAllocator::class)->next('opportunity_items');
            $path = $tree->nextChildPath($opportunity->id, $versionId, $principalPath);
            $quantity = bcmul((string) $accessory->quantity, $productQuantity, 2);

            ItemAdded::fire(
                opportunity_item_id: $accessoryId,
                opportunity_id: $opportunity->id,
                version_id: $versionId,
                itemable_id: $accessoryProduct->id,
                itemable_type: Product::class,
                item_type: OpportunityItemType::Accessory->value,
                path: $path,
                name: $accessoryProduct->name,
                quantity: $quantity,
                transaction_type: $transactionType,
                charge_period: $chargePeriod,
                starts_at: $startsAt,
                ends_at: $endsAt,
                is_optional: $isOptional,
            );
        }
    }
}
