<?php

namespace App\Data\Opportunities;

use Spatie\LaravelData\Data;

/**
 * Input DTO for checking a serialised asset back in (the RMS `check_in` action).
 */
class ReturnAssetData extends Data
{
    public function __construct(
        public ?int $received_by = null,
        public ?int $return_store_id = null,
        public ?string $returned_at = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'received_by' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'return_store_id' => ['sometimes', 'nullable', 'integer', 'exists:stores,id'],
            'returned_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
