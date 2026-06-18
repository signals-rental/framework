<?php

namespace App\Actions\Opportunities;

use App\Concerns\CommitsVerbsEvents;
use App\Data\Opportunities\ChangeItemDatesData;
use App\Data\Opportunities\OpportunityData;
use App\Models\OpportunityItem;
use App\Verbs\Events\Opportunities\ItemDatesChanged;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Changes a line item's per-item hire window via the ItemDatesChanged event.
 */
class ChangeItemDates
{
    use CommitsVerbsEvents;

    public function __invoke(OpportunityItem $item, ChangeItemDatesData $data): OpportunityData
    {
        Gate::authorize('opportunities.edit');

        $opportunity = $item->opportunity()->firstOrFail();

        // Resolve the effective window NOW (requested dates ?? opportunity dates ??
        // a single captured fire-time now()) and bake CONCRETE dates into the event
        // so the totals engine never calls now() in handle() — keeping replay totals
        // identical for a dateless rate-priced line.
        $now = Carbon::now('UTC');
        $startsAt = $data->starts_at
            ?? $this->toIso($opportunity->starts_at)
            ?? $now->toIso8601String();
        $endsAt = $data->ends_at
            ?? $this->toIso($opportunity->ends_at)
            ?? Carbon::parse($startsAt)->copy()->addDay()->toIso8601String();

        $this->commitVerbs(function () use ($item, $startsAt, $endsAt): void {
            ItemDatesChanged::fire(
                opportunity_item_id: $item->state_id,
                starts_at: $startsAt,
                ends_at: $endsAt,
            );
        });

        return OpportunityData::fromModel($opportunity->fresh(['items']));
    }

    private function toIso(?\DateTimeInterface $value): ?string
    {
        return $value !== null ? Carbon::parse($value)->toIso8601String() : null;
    }
}
