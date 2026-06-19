<?php

namespace App\Data\Opportunities;

use Spatie\LaravelData\Data;

/**
 * Input DTO for allocating a single serialised asset (stock level) to an
 * opportunity line item.
 */
class AllocateAssetData extends Data
{
    public function __construct(
        public int $stock_level_id,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'stock_level_id' => ['required', 'integer', 'exists:stock_levels,id'],
        ];
    }
}
