<?php

namespace App\Data\Shortages;

use Spatie\LaravelData\Data;

/**
 * Input for applying a shortage resolution to a line item: which resolver, and
 * which of its generated options (by index, defaulting to the first).
 */
class ApplyResolutionData extends Data
{
    public function __construct(
        public int $opportunity_item_id,
        public string $resolver_key,
        public int $option_index = 0,
        public ?string $notes = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'opportunity_item_id' => ['required', 'integer', 'exists:opportunity_items,id'],
            'resolver_key' => ['required', 'string', 'max:64'],
            'option_index' => ['sometimes', 'integer', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
