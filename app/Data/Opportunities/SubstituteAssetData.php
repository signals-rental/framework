<?php

namespace App\Data\Opportunities;

use Spatie\LaravelData\Data;

/**
 * Input DTO for substituting the physical asset an assignment points at.
 */
class SubstituteAssetData extends Data
{
    public function __construct(
        public int $new_stock_level_id,
        public ?string $reason = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'new_stock_level_id' => ['required', 'integer', 'exists:stock_levels,id'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
