<?php

namespace App\Observers;

use App\Enums\AvailabilityEventType;
use App\Models\AvailabilityEvent;
use App\Models\Demand;
use App\Models\Product;
use App\Services\Availability\RecalculationPipeline;
use Illuminate\Support\Carbon;

/**
 * Keeps availability snapshots consistent when demands change.
 *
 * M2 wiring is **synchronous**: every demand create/update/delete immediately
 * recalculates the affected product/store/period through the
 * {@see RecalculationPipeline} and appends a lifecycle event
 * (`demand_created` / `demand_released`). M3 makes this async/debounced — the
 * trigger point stays here.
 *
 * The blast radius is the union of the demand's old and new windows (with the
 * product's buffers baked in), so a period change recalculates both the slots
 * it left and the slots it entered. The pipeline writes only snapshots and
 * availability events — never demands — so there is no observer recursion.
 */
class DemandObserver
{
    public function __construct(
        private readonly RecalculationPipeline $pipeline,
    ) {}

    public function created(Demand $demand): void
    {
        $this->log($demand, AvailabilityEventType::DemandCreated);
        $this->recalculate($demand);
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

        $this->recalculate($demand);
    }

    public function deleted(Demand $demand): void
    {
        $this->log($demand, AvailabilityEventType::DemandReleased);
        $this->recalculate($demand);
    }

    /**
     * Recalculate snapshots across the union of the demand's previous and
     * current buffered windows.
     */
    private function recalculate(Demand $demand): void
    {
        $product = Product::query()->find($demand->product_id);

        if ($product === null) {
            return;
        }

        [$from, $to] = $this->affectedWindow($demand, $product);

        $this->pipeline->recalculate($demand->product_id, $demand->store_id, $from, $to);
    }

    /**
     * The half-open window to recalculate: the union of the original (pre-save)
     * and current buffered demand periods, so slot coverage spans any move.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function affectedWindow(Demand $demand, Product $product): array
    {
        $bufferBefore = (int) ($product->buffer_before_minutes ?? 0);
        $bufferAfter = (int) ($product->post_rent_unavailability ?? 0);

        [$currentStart, $currentEnd] = Demand::bufferedPeriod(
            Carbon::parse($demand->starts_at),
            Carbon::parse($demand->ends_at),
            $bufferBefore,
            $bufferAfter,
        );

        $earliestStart = $currentStart;
        $latestEnd = $currentEnd;

        $originalStart = $demand->getOriginal('starts_at');
        $originalEnd = $demand->getOriginal('ends_at');

        if ($originalStart !== null && $originalEnd !== null) {
            [$previousStart, $previousEnd] = Demand::bufferedPeriod(
                Carbon::parse($originalStart),
                Carbon::parse($originalEnd),
                $bufferBefore,
                $bufferAfter,
            );

            $earliestStart = $previousStart->lessThan($earliestStart) ? $previousStart : $earliestStart;
            $latestEnd = $previousEnd->greaterThan($latestEnd) ? $previousEnd : $latestEnd;
        }

        return [$earliestStart, $latestEnd];
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
