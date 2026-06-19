<?php

namespace App\Data\Opportunities;

use Spatie\LaravelData\Data;

/**
 * Input DTO for preparing several allocated assets in one atomic commit (the RMS
 * `quick_prepare` action, opportunity-lifecycle.md §11.1).
 *
 * @property list<int> $asset_ids opportunity_item_assets primary keys
 */
class QuickPrepareAssetsData extends Data
{
    /**
     * @param  list<int>  $asset_ids
     */
    public function __construct(
        public array $asset_ids,
        public ?string $prepared_at = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'asset_ids' => ['required', 'array', 'min:1'],
            'asset_ids.*' => ['required', 'integer', 'exists:opportunity_item_assets,id'],
            'prepared_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
