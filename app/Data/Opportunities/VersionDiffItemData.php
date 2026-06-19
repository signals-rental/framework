<?php

namespace App\Data\Opportunities;

use Spatie\LaravelData\Data;

/**
 * A single line in a version diff (opportunity-lifecycle.md §8.11).
 *
 * Represents one product's delta between two versions of the same opportunity.
 * For an `added`/`removed` line, only the corresponding side's figures are set;
 * for a `changed` line, both source and target figures are present so the caller
 * can show the before/after. Money figures are decimal strings (RMS format); the
 * `*_delta` figures are signed decimal strings.
 */
class VersionDiffItemData extends Data
{
    public function __construct(
        public ?int $item_id,
        public ?string $item_type,
        public string $name,
        public ?string $source_quantity,
        public ?string $target_quantity,
        public ?string $source_unit_price,
        public ?string $target_unit_price,
        public ?string $source_discount_percent,
        public ?string $target_discount_percent,
        public ?string $source_total,
        public ?string $target_total,
        public string $total_delta,
    ) {}
}
