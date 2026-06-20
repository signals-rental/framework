<?php

namespace App\ValueObjects;

use App\Enums\ShortageDispatchPolicy;

/**
 * The outcome of the dispatch shortage gate (shortage-resolution-sub-hires.md
 * §7.4; opportunity-lifecycle.md §7.1/§7.4).
 *
 * Evaluated at dispatch time — SEPARATE from the quote → order confirmation gate
 * ({@see ConfirmationGateResult}). Even an order knowingly confirmed short re-runs
 * its own check before stock leaves the warehouse, governed by the store's
 * {@see ShortageDispatchPolicy}:
 *
 *  - Block       — the gate blocks; the dispatch action throws a 422.
 *  - WarnPartial — the dispatch proceeds for what is available; the short line is
 *    held back and surfaced in the response (`warned` true).
 *  - AllowPartial— the dispatch proceeds silently (`warned` false).
 *
 * `shortages` carries the short lines that drove the decision so a Block response
 * can list them and a Warn response can flag the held-back items.
 */
final readonly class DispatchGateResult
{
    /**
     * @param  ShortageCollection  $shortages  The short lines in the dispatch (empty when nothing is short).
     */
    public function __construct(
        public ShortageDispatchPolicy $policy,
        public ShortageCollection $shortages,
    ) {}

    /**
     * Whether any line in the dispatch is short.
     */
    public function isShort(): bool
    {
        return $this->shortages->hasUnresolved();
    }

    /**
     * Whether the gate blocks the dispatch — a Block policy with an unresolved
     * shortage on the dispatched line(s).
     */
    public function blocks(): bool
    {
        return $this->policy->blocksDispatch() && $this->isShort();
    }

    /**
     * Whether the gate warns about held-back items — a WarnPartial policy with an
     * unresolved shortage (AllowPartial holds silently, Block never reaches here).
     */
    public function warned(): bool
    {
        return $this->policy->warnsOnPartial() && $this->isShort();
    }

    /**
     * The held-item metadata surfaced on a WarnPartial dispatch response so the UI
     * can flag what was held back. Empty unless the gate warned.
     *
     * @return array<string, mixed>
     */
    public function toHeldItemsMeta(): array
    {
        if (! $this->warned()) {
            return [];
        }

        $heldItems = [];

        foreach ($this->shortages->unresolved() as $shortage) {
            $heldItems[] = $shortage->toBadge();
        }

        return [
            'dispatch_policy' => $this->policy->value,
            'held_items' => $heldItems,
        ];
    }
}
