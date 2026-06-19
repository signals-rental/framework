<?php

namespace App\Data\Opportunities;

use Spatie\LaravelData\Data;

/**
 * Structured item-level diff between two versions of the same opportunity
 * (opportunity-lifecycle.md §8.11).
 *
 * Computed on-demand from the projections (never stored). Lines are matched by
 * product (`item_id`). `net_change` is the signed total-value delta (target minus
 * source) as a decimal string.
 */
class VersionDiffData extends Data
{
    public function __construct(
        public int $source_version_id,
        public int $target_version_id,
        public int $source_version_number,
        public int $target_version_number,
        /** @var array<int, VersionDiffItemData> */
        public array $added,
        /** @var array<int, VersionDiffItemData> */
        public array $removed,
        /** @var array<int, VersionDiffItemData> */
        public array $changed,
        public string $source_total,
        public string $target_total,
        public string $net_change,
    ) {}
}
