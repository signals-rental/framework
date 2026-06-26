<?php

namespace App\Data\Opportunities;

use App\Services\Opportunities\ProductSearchService;
use Spatie\LaravelData\Data;

/**
 * A single product hit for the opportunity line-item product picker.
 *
 * Returned by {@see ProductSearchService::search()}
 * and consumed by the editor's two-tier search (instant client-side MiniSearch
 * index + a debounced server fallback over the Postgres `pg_trgm` index). Shape
 * is deliberately lightweight — only what a picker row needs.
 *
 * MONEY: `default_rate` is the product's resolved per-unit rental rate as a
 * decimal string in major units (RMS format, e.g. `"125.50"`), derived from the
 * stored integer minor units at the serialisation boundary. Null when the product
 * has no resolvable rate (an ad-hoc / unpriced product).
 *
 * AVAILABILITY: `availability` is the point status for the requested store —
 * `available`, `reserved`, or `out` — or null when no store was supplied (the
 * picker then renders no chip). Computed live from the demands table.
 */
class ProductSearchResultData extends Data
{
    /**
     * @param  array<int, ProductSearchAccessoryData>  $accessories  Linked accessories (name, sku, ratio)
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?string $sku,
        public ?string $default_rate,
        public array $accessories = [],
        public ?string $availability = null,
        public ?string $image_url = null,
        public string $initials = '',
    ) {}
}
