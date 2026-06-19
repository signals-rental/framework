<?php

namespace App\Data\Opportunities;

use Spatie\LaravelData\Data;

/**
 * Input DTO for booking several serialised assets out in one atomic commit (the
 * RMS `quick_book_out` action).
 *
 * @property list<int> $asset_ids opportunity_item_assets primary keys
 */
class QuickBookOutData extends Data
{
    /**
     * @param  list<int>  $asset_ids
     */
    public function __construct(
        public array $asset_ids,
        public ?int $dispatched_by = null,
        public ?int $vehicle_id = null,
        public ?string $dispatched_at = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'asset_ids' => ['required', 'array', 'min:1'],
            'asset_ids.*' => ['required', 'integer', 'exists:opportunity_item_assets,id'],
            'dispatched_by' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'vehicle_id' => ['sometimes', 'nullable', 'integer'],
            'dispatched_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
