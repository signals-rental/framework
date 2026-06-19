<?php

namespace App\Services\Opportunities;

use App\Actions\Opportunities\QuickBookOut;
use App\Actions\Opportunities\QuickCheckIn;

/**
 * Request-scoped suppression flag for asset/bulk auto-promotion.
 *
 * The single-asset and single-bulk events auto-promote the parent opportunity from
 * their own `fired()` hook. In a BATCH wrapper ({@see QuickBookOut}
 * / {@see QuickCheckIn}) those per-event promotions are
 * noise — each event can only see itself, so they would emit a string of
 * intermediate promotions before the wrapper's single authoritative one. The
 * wrapper {@see suppress()}es per-event promotion for the duration of the batch and
 * fires exactly one final promotion with the whole batch overlaid.
 *
 * Resolved from the container ({@see app()}) so it is per-request scoped (and
 * Octane-safe — no shared static), and mockable in tests.
 */
class AutoPromotionContext
{
    private int $depth = 0;

    /**
     * Run the given callback with per-event auto-promotion suppressed.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function suppress(callable $callback): mixed
    {
        $this->depth++;

        try {
            return $callback();
        } finally {
            $this->depth--;
        }
    }

    /**
     * Whether per-event auto-promotion is currently suppressed (inside a batch).
     */
    public function isSuppressed(): bool
    {
        return $this->depth > 0;
    }
}
