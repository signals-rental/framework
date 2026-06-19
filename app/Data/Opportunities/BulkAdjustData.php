<?php

namespace App\Data\Opportunities;

use Spatie\LaravelData\Data;

/**
 * Input DTO for adjusting the requested quantity of a bulk line mid-cycle.
 */
class BulkAdjustData extends Data
{
    public function __construct(
        public string $new_quantity,
        public ?string $reason = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'new_quantity' => ['required', 'numeric', 'gte:0'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
