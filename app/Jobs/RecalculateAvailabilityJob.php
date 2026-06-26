<?php

namespace App\Jobs;

use App\Events\Availability\AvailabilityChanged;
use App\Events\Availability\OpportunityAvailabilityChanged;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Services\Api\WebhookService;
use App\Services\Availability\OpportunityItemDemandResolver;
use App\Services\Availability\RecalculationPipeline;
use App\Services\AvailabilityService;
use App\Services\Shortages\ShortageDetector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Debounced, asynchronous availability recompute for a single product/store.
 *
 * The demand and stock observers no longer recompute snapshots inline; they
 * enqueue this job, which runs the {@see RecalculationPipeline} over the rolling
 * snapshot horizon for the carried product/store and then broadcasts an
 * {@see AvailabilityChanged} Reverb event so live calendar/grid views refresh.
 *
 * **Debouncing.** The job is {@see ShouldBeUnique}: its {@see uniqueId()} is
 * `availability:{productId}:{storeId}` and the lock is held for
 * {@see uniqueFor()} seconds. A burst of demand/stock changes for the same
 * product/store therefore coalesces into a single queued recompute instead of
 * one per write — the pipeline reads the *current* demand/stock state when it
 * eventually runs, so a single late run reflects the whole burst.
 *
 * **Idempotent.** The pipeline upserts snapshots keyed by product/store/slot and
 * rolls daily summaries with `updateOrCreate`, so re-running the job (a retry, or
 * a second dispatch after the lock clears) converges to the same result.
 *
 * **Replay-safe.** This job is dispatched only by Eloquent observers on real
 * demand/stock writes. During a `Verbs::replay()` no demand rows are written
 * (sync is `Verbs::unlessReplaying()`-guarded) and the observers additionally
 * short-circuit on replay, so neither the recompute nor the broadcast happens
 * while rebuilding the event store.
 *
 * Point availability ({@see AvailabilityService::getAvailability()})
 * is unaffected by the async switch: it reads the authoritative `demands` table
 * live and never depends on snapshots, so it stays exact. Only the
 * snapshot/range/calendar read model is eventually-consistent.
 */
class RecalculateAvailabilityJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Recompute is cheap and idempotent; a single attempt is enough. A failure
     * is re-triggered by the next demand/stock change or the next overdue sweep.
     */
    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public int $productId,
        public int $storeId,
    ) {
        $this->onQueue((string) config('availability.recalc.queue', 'availability'));
    }

    /**
     * Coalesce concurrent dispatches for the same product/store onto one job.
     */
    public function uniqueId(): string
    {
        return 'availability:'.$this->productId.':'.$this->storeId;
    }

    /**
     * How long (seconds) the uniqueness lock is held — the debounce window.
     */
    public function uniqueFor(): int
    {
        return max(1, (int) config('availability.recalc.debounce_seconds', 2));
    }

    /**
     * Prevent two recomputes for the SAME product/store from running
     * concurrently (a debounced dispatch can clear the {@see ShouldBeUnique} lock
     * and enqueue a second job while the first is still mid-recalc).
     * {@see WithoutOverlapping} keyed on `product_id:store_id` serialises them;
     * `dontRelease()` drops the colliding attempt rather than re-queueing it,
     * since the running job already reads the current demand/stock state. The
     * Postgres advisory lock guards cross-process interleaving at the DB layer;
     * this guards the worker layer.
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->productId.':'.$this->storeId))
                ->dontRelease()
                ->expireAfter($this->timeout + 60),
        ];
    }

    /**
     * Recalculate the rolling snapshot horizon for the product/store, then
     * broadcast the change. No-ops (untracked product, empty horizon) skip the
     * broadcast.
     */
    public function handle(RecalculationPipeline $pipeline, WebhookService $webhooks, ShortageDetector $detector): void
    {
        [$from, $to] = $pipeline->fullHorizon();

        $result = $pipeline->recalculate($this->productId, $this->storeId, $from, $to);

        if (! $result->recalculated()) {
            return;
        }

        AvailabilityChanged::dispatch(
            $this->productId,
            $this->storeId,
            $result->from?->toIso8601String(),
            $result->to?->toIso8601String(),
            $result->slots,
            $result->hasShortage,
        );

        // Also broadcast on each affected opportunity's own channel
        // (availability.opportunity.{id}) so the M8 opportunity Show page gets
        // live per-line availability without subscribing to every product/store
        // channel. One broadcast per distinct opportunity holding an active demand
        // for this product/store over the refreshed window.
        $this->broadcastToOpportunities($result->from, $result->to, $result->hasShortage, $detector);

        // Mirror the Reverb broadcast onto the outbound webhook bus so external
        // integrators can react to availability changes too. This runs in a
        // queued job that the (replay-skipped) observers enqueue, so it is never
        // reached during a Verbs replay — no extra guard is required here.
        $webhooks->dispatch('availability.changed', [
            'product_id' => $this->productId,
            'store_id' => $this->storeId,
            'from' => $result->from?->toIso8601String(),
            'to' => $result->to?->toIso8601String(),
            'slots' => $result->slots,
            'has_shortage' => $result->hasShortage,
        ]);
    }

    /**
     * Broadcast an {@see OpportunityAvailabilityChanged} on the channel of every
     * opportunity that holds an active `opportunity_item` demand for this
     * product/store over the refreshed window.
     *
     * Opportunity ids are read from the active demands' `metadata.opportunity_id`
     * (stamped by the {@see OpportunityItemDemandResolver}),
     * de-duplicated so a multi-line opportunity gets a single broadcast. When the
     * window is unknown (a skipped clamp) the broadcast is omitted — the
     * product/store broadcast above still fires.
     */
    private function broadcastToOpportunities(?Carbon $from, ?Carbon $to, bool $hasShortage, ShortageDetector $detector): void
    {
        if ($from === null || $to === null) {
            return;
        }

        /** @var array<int, true> $opportunityIds */
        $opportunityIds = [];

        Demand::query()
            ->where('product_id', $this->productId)
            ->where('store_id', $this->storeId)
            ->where('source_type', 'opportunity_item')
            ->active()
            ->overlapping($from, $to)
            ->get(['id', 'source_id', 'metadata'])
            ->each(function (Demand $demand) use (&$opportunityIds): void {
                $opportunityId = $demand->metadata['opportunity_id'] ?? null;

                if ($opportunityId !== null) {
                    $opportunityIds[(int) $opportunityId] = true;

                    return;
                }

                // An opportunity_item demand without a metadata.opportunity_id is a
                // data-consistency defect (a third-party plugin or a test factory
                // that omitted the key): the opportunity cannot be broadcast to. Log
                // it so the gap surfaces rather than failing silently.
                Log::warning('opportunity_item demand missing metadata.opportunity_id; skipped opportunity broadcast.', [
                    'demand_id' => $demand->id,
                    'source_id' => $demand->source_id,
                    'product_id' => $this->productId,
                    'store_id' => $this->storeId,
                ]);
            });

        foreach (array_keys($opportunityIds) as $opportunityId) {
            // Maintain the denormalised `opportunities.has_shortage` flag for the
            // list/Show badge and the `q[has_shortage_true]` filter. The recalc
            // may have introduced OR cleared a shortage on this opportunity, so
            // re-run the authoritative opportunity-scoped detector (which nets off
            // active resolutions) rather than trusting the product/store-scoped
            // `$hasShortage` summary. Replay-safe: this job is dispatched only by
            // the replay-skipped demand/stock observers.
            $opportunityHasShortage = $this->refreshOpportunityShortageFlag($opportunityId, $detector);

            OpportunityAvailabilityChanged::dispatch(
                $opportunityId,
                $this->productId,
                $this->storeId,
                $from->toIso8601String(),
                $to->toIso8601String(),
                $opportunityHasShortage,
            );
        }
    }

    /**
     * Recompute and persist the denormalised `has_shortage` flag for one
     * opportunity, writing only when it actually changed (quiet update — no model
     * events, no further demand recalcs). Returns the resolved flag so the caller
     * can broadcast the opportunity-accurate value. A missing opportunity row (a
     * stale demand) is a no-op returning false.
     */
    private function refreshOpportunityShortageFlag(int $opportunityId, ShortageDetector $detector): bool
    {
        $opportunity = Opportunity::query()->whereKey($opportunityId)->first();

        if ($opportunity === null) {
            return false;
        }

        $hasShortage = $detector->forOpportunity($opportunity)->hasUnresolved();

        if ($opportunity->has_shortage !== $hasShortage) {
            $opportunity->forceFill(['has_shortage' => $hasShortage])->saveQuietly();
        }

        return $hasShortage;
    }
}
