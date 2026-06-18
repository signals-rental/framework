<?php

namespace App\Data\Opportunities;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

class UpdateOpportunityData extends Data
{
    public function __construct(
        public ?string $subject = null,
        public ?int $member_id = null,
        public ?int $venue_id = null,
        public ?int $store_id = null,
        public ?int $owned_by = null,
        public ?string $reference = null,
        public ?string $description = null,
        public ?string $external_description = null,
        public ?string $starts_at = null,
        public ?string $ends_at = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'subject' => ['sometimes', 'nullable', 'string', 'max:255'],
            'member_id' => ['sometimes', 'nullable', 'integer', Rule::exists('members', 'id')->withoutTrashed()],
            'venue_id' => ['sometimes', 'nullable', 'integer', Rule::exists('members', 'id')->withoutTrashed()],
            'owned_by' => ['sometimes', 'nullable', 'integer', Rule::exists('members', 'id')->withoutTrashed()],
            'store_id' => ['sometimes', 'nullable', 'integer', 'exists:stores,id'],
            'reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'external_description' => ['sometimes', 'nullable', 'string'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }
}
