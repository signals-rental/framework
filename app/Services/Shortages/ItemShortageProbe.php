<?php

namespace App\Services\Shortages;

use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ChangeItemDates;
use App\Enums\AvailabilityEventType;
use App\Enums\StockMethod;
use App\Models\AvailabilityEvent;
use App\Models\OpportunityItem;
use App\ValueObjects\Shortage;
use App\ValueObjects\ShortageCollection;
use Illuminate\Support\Carbon;
use Thunk\Verbs\Facades\Verbs;

/**
 * The inline (reactive) detection trigger for line-item add/edit
 * (shortage-resolution-sub-hires.md §2.4 "On line item addition or edit / On date
 * changes").
 *
 * Called from the {@see AddOpportunityItem} and
 * {@see ChangeItemDates} actions AFTER the Verbs event
 * commits, so the item's projection (and any resulting demand) already reflects
 * the change. It runs one cheap {@see ShortageDetector::forItem()} check and emits
 * `shortage.detected` when a shortfall now exists, or `shortage.cleared` when one
 * previously existed and no longer does. It never blocks the action on resolution
 * — the action's success does not depend on this probe.
 *
 * Replay-safe: detection is skipped entirely during `Verbs::isReplaying()` so a
 * truncate + replay rebuild never re-emits telemetry into `availability_events`.
 */
class ItemShortageProbe
{
    public function __construct(
        private readonly ShortageDetector $detector,
        private readonly ShortageEventRecorder $events,
    ) {}

    /**
     * Probe a single line item, emitting the appropriate detected/cleared event.
     */
    public function probe(OpportunityItem $item): void
    {
        if (Verbs::isReplaying()) {
            return;
        }

        $shortage = $this->detector->forItem($item);

        if ($shortage !== null && $shortage->isUnresolved()) {
            $this->events->detected(new ShortageCollection([$shortage]));

            return;
        }

        // No shortage now — only emit a cleared event when this line previously
        // had an OPEN shortage (a detected event with no later clear), so we don't
        // spam clears for lines that were never short (§9.1 shortage.cleared).
        if ($this->hasOpenShortage($item)) {
            $this->events->cleared(
                $this->placeholderShortage($item),
                reason: 'item_changed',
            );
        }
    }

    /**
     * Whether the line item has an outstanding (not-yet-cleared) shortage in the
     * availability event log — its most recent shortage event is a detection.
     */
    private function hasOpenShortage(OpportunityItem $item): bool
    {
        return $this->hasOpenShortageForItemId($item->id);
    }

    /**
     * Whether a line item (by id) has an outstanding (not-yet-cleared) shortage in
     * the availability event log — its most recent shortage event is a detection.
     *
     * The canonical open-shortage check, shared with the proactive
     * {@see App\Listeners\Availability\DetectOrderShortages} listener so a standing
     * shortage is announced once across recalcs rather than on every recalc.
     */
    public function hasOpenShortageForItemId(int $opportunityItemId): bool
    {
        $latest = AvailabilityEvent::query()
            ->where('source_type', 'opportunity_item')
            ->where('source_id', $opportunityItemId)
            ->whereIn('event_type', [
                AvailabilityEventType::ShortageDetected->value,
                AvailabilityEventType::ShortageResolved->value,
            ])
            ->latest('id')
            ->value('event_type');

        // `event_type` is cast to AvailabilityEventType on the model, so
        // ->value('event_type') returns the enum instance (or null), which we
        // compare against the enum case.
        return $latest === AvailabilityEventType::ShortageDetected;
    }

    /**
     * Build a minimal Shortage carrying the line's identity for a cleared event
     * when the detector reports no current shortfall. The quantities are zeroed —
     * the cleared event only needs product/store/item/window context.
     */
    private function placeholderShortage(OpportunityItem $item): Shortage
    {
        $opportunity = $item->opportunity;
        $startsAt = $item->starts_at ?? $opportunity->starts_at ?? now();
        $endsAt = $item->ends_at ?? $opportunity->ends_at ?? now()->addDay();

        return Shortage::make(
            opportunityItemId: $item->id,
            opportunityId: (int) $item->opportunity_id,
            productId: (int) ($item->item_id ?? 0),
            productName: (string) $item->name,
            storeId: (int) ($opportunity->store_id ?? 0),
            requestedQuantity: 0,
            availableQuantity: 0,
            trackingType: StockMethod::Bulk,
            startsAt: Carbon::parse($startsAt),
            endsAt: Carbon::parse($endsAt),
            isCritical: false,
        );
    }
}
