<?php

namespace App\Data\Opportunities;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class UpdateOpportunityData extends Data
{
    /**
     * The nullable header fields use {@see Optional} so the action can tell an
     * ABSENT field (leave the column unchanged) apart from an EXPLICIT null
     * (clear the column). Required-by-presence scalars stay plain-nullable.
     */
    public function __construct(
        public ?string $subject = null,
        public ?int $member_id = null,
        public int|null|Optional $venue_id = new Optional,
        public ?int $store_id = null,
        public ?int $owned_by = null,
        public string|null|Optional $reference = new Optional,
        public string|null|Optional $description = new Optional,
        public string|null|Optional $external_description = new Optional,
        public ?string $starts_at = null,
        public ?string $ends_at = null,
        public ?string $charge_starts_at = null,
        public ?string $charge_ends_at = null,
        /** @var list<string>|null|Optional */
        public array|null|Optional $tag_list = new Optional,
        /** @var array<string, mixed>|null */
        public ?array $custom_fields = null,
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
            'charge_starts_at' => ['sometimes', 'nullable', 'date'],
            'charge_ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:charge_starts_at'],
            'tag_list' => ['sometimes', 'nullable', 'array'],
            'tag_list.*' => ['string', 'max:255'],
            'custom_fields' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
