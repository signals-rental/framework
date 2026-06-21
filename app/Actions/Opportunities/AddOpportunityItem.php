<?php

namespace App\Actions\Opportunities;

use App\Actions\Opportunities\Concerns\FormatsOpportunityDates;
use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\OpportunityData;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
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

        // Resolve the display position. An explicit sort_order (clone/version paths
        // preserving the source order) is honoured; otherwise the line is appended
        // after the opportunity's existing items in the same version scope. The
        // resolved value is baked into the ItemAdded event below, so a Verbs replay
        // reproduces the identical position.
        $sortOrder = $data->sort_order ?? $this->nextSortOrder($opportunity->id, $versionId);

        $this->commitVerbs(function () use ($opportunity, $data, $startsAt, $endsAt, $versionId, $sortOrder, &$itemId): void {
            // Allocate the replay-stable small PK and bake it into the event so a
            // truncate + Verbs::replay() rebuild reproduces the identical id.
            $itemId = app(SequenceAllocator::class)->next('opportunity_items');

            ItemAdded::fire(
                opportunity_item_id: $itemId,
                opportunity_id: $opportunity->id,
                version_id: $versionId,
                item_id: $data->item_id,
                item_type: $data->item_type,
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
                sort_order: $sortOrder,
                custom_fields: $data->custom_fields,
                notes: $data->notes,
            );
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

    /**
     * The next display position for an appended line: one past the highest existing
     * `sort_order` in the same opportunity + version scope, or 0 for the first line.
     */
    private function nextSortOrder(int $opportunityId, ?int $versionId): int
    {
        $max = OpportunityItem::query()
            ->where('opportunity_id', $opportunityId)
            ->when(
                $versionId !== null,
                fn ($query) => $query->where('version_id', $versionId),
                fn ($query) => $query->whereNull('version_id'),
            )
            ->max('sort_order');

        return $max === null ? 0 : ((int) $max) + 1;
    }
}
