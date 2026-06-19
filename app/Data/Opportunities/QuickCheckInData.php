<?php

namespace App\Data\Opportunities;

use Spatie\LaravelData\Data;

/**
 * Input DTO for checking several serialised assets back in in one atomic commit
 * (the RMS `quick_check_in` action).
 *
 * When `finalise` is true each return is immediately followed by a condition check
 * (condition defaults to Good) — mirroring the RMS `finalise_check_in`.
 *
 * @property list<int> $asset_ids opportunity_item_assets primary keys
 */
class QuickCheckInData extends Data
{
    /**
     * @param  list<int>  $asset_ids
     */
    public function __construct(
        public array $asset_ids,
        public ?int $received_by = null,
        public ?int $return_store_id = null,
        public bool $finalise = false,
        public ?string $returned_at = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'asset_ids' => ['required', 'array', 'min:1'],
            'asset_ids.*' => ['required', 'integer', 'exists:opportunity_item_assets,id'],
            'received_by' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'return_store_id' => ['sometimes', 'nullable', 'integer', 'exists:stores,id'],
            'finalise' => ['sometimes', 'boolean'],
            'returned_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
