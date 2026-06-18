<?php

namespace App\Services\Availability;

use App\Jobs\RecalculateAvailabilityJob;
use Illuminate\Support\Carbon;

/**
 * The outcome of a single {@see RecalculationPipeline::recalculate()} run.
 *
 * Carries the (clamped) window that was actually materialised and the number of
 * slots written, so callers — chiefly {@see RecalculateAvailabilityJob}
 * — can build a broadcast/audit summary without re-querying. A `slots` of zero
 * means the pipeline no-opped (product not tracked, or the requested window lay
 * entirely outside the rolling horizon).
 */
final readonly class RecalculationResult
{
    public function __construct(
        public int $productId,
        public int $storeId,
        public ?Carbon $from,
        public ?Carbon $to,
        public int $slots,
    ) {}

    /**
     * A no-op result: nothing was materialised for this product/store.
     */
    public static function skipped(int $productId, int $storeId): self
    {
        return new self($productId, $storeId, null, null, 0);
    }

    /**
     * Whether any snapshots were actually written.
     */
    public function recalculated(): bool
    {
        return $this->slots > 0;
    }
}
