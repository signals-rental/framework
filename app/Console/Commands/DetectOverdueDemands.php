<?php

namespace App\Console\Commands;

use App\Enums\AvailabilityEventType;
use App\Jobs\RecalculateAvailabilityJob;
use App\Models\AvailabilityEvent;
use App\Models\Demand;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Extends overdue, still-unreturned demands to the sentinel "no known end" date.
 *
 * An **active** demand (phase Committed/Operational, cached in `is_active`) whose
 * scheduled `ends_at` has already passed — but which has NOT been closed by a
 * return — describes stock that should be back on the shelf yet physically is
 * not. Left as-is, its window would lapse and availability would wrongly free the
 * item for future bookings. This command pushes such a demand's `ends_at` out to
 * {@see Demand::SENTINEL_DATE} so the unavailable window stays open until an
 * actual return is recorded (which resets `ends_at` to the real return time).
 *
 * The "still unreturned" criterion is exactly the `active()` scope: a returned or
 * cancelled demand has already transitioned to Closed/Void (inactive) and is
 * skipped. Demands already at the sentinel (open-ended hires, prior overdue
 * extensions) are skipped via {@see Demand::scopeDefinite()}.
 *
 * Idempotent and safe to re-run: a demand extended on one pass is sentinel-dated
 * and therefore excluded from the next. Each extension logs a `demand_overdue`
 * availability event and enqueues a debounced {@see RecalculateAvailabilityJob}
 * for the affected product/store. Registered on the scheduler hourly.
 */
#[AsCommand(name: 'availability:detect-overdue-demands')]
class DetectOverdueDemands extends Command
{
    protected $signature = 'availability:detect-overdue-demands';

    protected $description = 'Extend overdue, unreturned demands to the sentinel date so availability stays accurate';

    public function handle(): int
    {
        $now = Carbon::now('UTC');
        $batchSize = max(1, (int) config('availability.overdue.batch_size', 500));

        $overdue = Demand::query()
            ->active()
            ->definite()
            ->where('ends_at', '<', $now)
            ->orderBy('id')
            ->limit($batchSize)
            ->get();

        if ($overdue->isEmpty()) {
            $this->info('No overdue demands found.');

            return self::SUCCESS;
        }

        $sentinel = Demand::sentinel();

        /** @var array<string, array{0: int, 1: int}> $affected */
        $affected = [];

        foreach ($overdue as $demand) {
            $scheduledEnd = Carbon::parse($demand->ends_at)->toIso8601String();

            // Extend the unavailable window to the sentinel and record the audit
            // event ATOMICALLY: the extension and its `demand_overdue` log must
            // commit together or not at all. saveQuietly() suppresses the
            // DemandObserver's per-save recalc dispatch — we dispatch ONCE per
            // product/store below (the observer's dispatch would otherwise fire a
            // redundant, un-debounced enqueue in addition to ours). is_active and
            // phase are unchanged — the item is still committed/operational, just late.
            DB::transaction(function () use ($demand, $sentinel, $scheduledEnd): void {
                $demand->ends_at = $sentinel;
                $demand->saveQuietly();

                $this->logOverdue($demand, $scheduledEnd);
            });

            // De-duplicate recalc dispatches per product/store; the job itself is
            // also debounced, but collapsing here avoids redundant enqueues when a
            // store has many overdue demands for the same product.
            $key = $demand->product_id.':'.$demand->store_id;
            $affected[$key] = [(int) $demand->product_id, (int) $demand->store_id];
        }

        foreach ($affected as [$productId, $storeId]) {
            RecalculateAvailabilityJob::dispatch($productId, $storeId);
        }

        $this->info(sprintf(
            'Extended %d overdue demand(s) to the sentinel; queued %d recalculation(s).',
            $overdue->count(),
            count($affected),
        ));

        return self::SUCCESS;
    }

    /**
     * Append a `demand_overdue` availability event recording the extension.
     */
    private function logOverdue(Demand $demand, string $scheduledEnd): void
    {
        AvailabilityEvent::query()->create([
            'event_type' => AvailabilityEventType::DemandOverdue,
            'product_id' => $demand->product_id,
            'store_id' => $demand->store_id,
            'demand_id' => $demand->id,
            'source_type' => $demand->source_type,
            'source_id' => $demand->source_id,
            'payload' => [
                'scheduled_ends_at' => $scheduledEnd,
                'extended_to' => Demand::SENTINEL_DATE,
                'phase' => $demand->phase->value,
                'quantity' => $demand->quantity,
            ],
        ]);
    }
}
