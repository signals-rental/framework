<?php

namespace App\Data\Opportunities;

use Spatie\LaravelData\Data;

/**
 * Input DTO for booking a serialised asset out (the RMS `book_out` action).
 */
class DispatchAssetData extends Data
{
    public function __construct(
        public ?int $dispatched_by = null,
        public ?int $vehicle_id = null,
        public ?string $notes = null,
        public ?string $dispatched_at = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'dispatched_by' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'vehicle_id' => ['sometimes', 'nullable', 'integer'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'dispatched_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
