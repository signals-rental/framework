<?php

namespace App\Data\Opportunities;

use Spatie\LaravelData\Data;

/**
 * Input DTO for a (partial) bulk-line dispatch.
 */
class BulkDispatchData extends Data
{
    public function __construct(
        public string $quantity,
        public ?int $dispatched_by = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'quantity' => ['required', 'numeric', 'gt:0'],
            'dispatched_by' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ];
    }
}
