<?php

namespace App\Observers;

use App\Enums\AvailabilityEventType;
use App\Jobs\RecalculateAvailabilityJob;
use App\Models\AvailabilityEvent;
use App\Models\Demand;
use App\Services\Availability\RecalculationPipeline;
use Illuminate\Support\Carbon;
use Thunk\Verbs\Facades\Verbs;

/**
 * Keeps availability snapshots consistent when demands change.
 *
 * M3-4 wiring is **asynchronous/debounced**: every demand create/update/delete
 * appends a lifecycle event (`demand_created` / `demand_updated` /
 * `demand_released`) and then enqueues a {@see RecalculateAvailabilityJob} for
 * the affected product/store. The job — not the observer — runs the
 * {@see RecalculationPipeline} over the rolling
 * horizon, on a Horizon-managed queue, coalescing a burst of changes for the
 * same product/store into a single recompute.
 *
 * Because the job recomputes the *whole* rolling horizon for the product/store
 * (reading the current demand state when it runs), the observer no longer needs
 * to compute a per-change blast window — a period move is covered automatically.
 *
 * **Replay-safety.** Demand sync is wrapped in `Verbs::unlessReplaying()`
 * upstream, so no demand rows are written during a `Verbs::replay()` and this
 * observer never fires there. As belt-and-suspenders the dispatch path also
 * short-circuits when Verbs is replaying, so even a direct demand write during
 * replay would not enqueue a recompute.
 *
 * The job writes only snapshots and availability events — never demands — so
 * there is no observer recursion.
 */
class DemandObserver
{
    public function created(Demand $demand): void
    {
        $this->log($demand, AvailabilityEventType::DemandCreated);
        $this->dispatchRecalculation($demand);
    }

    public function updated(Demand $demand): void
    {
        // A demand that has just become inactive (released) is logged as such;
        // otherwise the change is a plain update.
        $becameInactive = $demand->wasChanged('is_active') && ! $demand->is_active;

        $this->log(
            $demand,
            $becameInactive ? AvailabilityEventType::DemandReleased : AvailabilityEventType::DemandUpdated,
        );

        $this->dispatchRecalculation($demand);
    }

    public function deleted(Demand $demand): void
    {
        $this->log($demand, AvailabilityEventType::DemandReleased);
        $this->dispatchRecalculation($demand);
    }

    /**
     * Enqueue a debounced availability recompute for the demand's product/store.
     * Skipped during a Verbs replay so rebuilding the event store never fans out
     * recomputes or broadcasts.
     */
    private function dispatchRecalculation(Demand $demand): void
    {
        if (Verbs::isReplaying()) {
            return;
        }

        RecalculateAvailabilityJob::dispatch((int) $demand->product_id, (int) $demand->store_id);
    }

    /**
     * Append an availability event describing the demand change.
     */
    private function log(Demand $demand, AvailabilityEventType $type): void
    {
        AvailabilityEvent::query()->create([
            'event_type' => $type,
            'product_id' => $demand->product_id,
            'store_id' => $demand->store_id,
            'demand_id' => $demand->id,
            'source_type' => $demand->source_type,
            'source_id' => $demand->source_id,
            'payload' => [
                'quantity' => $demand->quantity,
                'phase' => $demand->phase->value,
                'is_active' => $demand->is_active,
                'starts_at' => Carbon::parse($demand->starts_at)->toIso8601String(),
                'ends_at' => Carbon::parse($demand->ends_at)->toIso8601String(),
            ],
        ]);
    }
}
