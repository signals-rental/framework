<?php

namespace App\ValueObjects;

use App\Services\Shortages\ShortageDetector;
use Illuminate\Support\Collection;

/**
 * A typed collection of {@see Shortage} value objects, returned by the
 * {@see ShortageDetector} (shortage-resolution-sub-hires.md
 * §2.2). Extends the framework Collection so the full higher-order API is
 * available while the element type is pinned for static analysis.
 *
 * @extends Collection<int, Shortage>
 */
final class ShortageCollection extends Collection
{
    /**
     * Whether the collection holds any shortage with unresolved remaining
     * shortfall — the question the confirmation gate asks.
     */
    public function hasUnresolved(): bool
    {
        return $this->contains(static fn (Shortage $shortage): bool => $shortage->isUnresolved());
    }

    /**
     * Only the shortages that still have unresolved shortfall.
     */
    public function unresolved(): self
    {
        return $this->filter(static fn (Shortage $shortage): bool => $shortage->isUnresolved())->values();
    }

    /**
     * The inline-badge payload for every shortage, keyed by opportunity item id
     * so the UI can map line rows to their badge in one pass.
     *
     * @return array<int, array<string, mixed>>
     */
    public function toBadges(): array
    {
        $badges = [];

        foreach ($this as $shortage) {
            $badges[$shortage->opportunityItemId] = $shortage->toBadge();
        }

        return $badges;
    }

    /**
     * The acknowledgement-snapshot payload for every shortage (§7.3).
     *
     * @return list<array<string, mixed>>
     */
    public function toSnapshots(): array
    {
        $snapshots = [];

        foreach ($this as $shortage) {
            $snapshots[] = $shortage->toSnapshot();
        }

        return $snapshots;
    }
}
