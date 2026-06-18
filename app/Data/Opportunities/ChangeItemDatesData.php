<?php

namespace App\Data\Opportunities;

use Spatie\LaravelData\Data;

/**
 * Input DTO for changing a line item's per-item hire window. A null date inherits
 * the parent opportunity's corresponding date.
 */
class ChangeItemDatesData extends Data
{
    public function __construct(
        public ?string $starts_at = null,
        public ?string $ends_at = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }
}
