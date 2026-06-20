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
        // Event-logistics lifecycle dates (C3a). Plain-nullable: null = leave
        // unchanged (these dates are set/moved, never "cleared" via this path).
        public ?string $prep_starts_at = null,
        public ?string $prep_ends_at = null,
        public ?string $load_starts_at = null,
        public ?string $load_ends_at = null,
        public ?string $deliver_starts_at = null,
        public ?string $deliver_ends_at = null,
        public ?string $setup_starts_at = null,
        public ?string $setup_ends_at = null,
        public ?string $show_starts_at = null,
        public ?string $show_ends_at = null,
        public ?string $takedown_starts_at = null,
        public ?string $takedown_ends_at = null,
        public ?string $collect_starts_at = null,
        public ?string $collect_ends_at = null,
        public ?string $unload_starts_at = null,
        public ?string $unload_ends_at = null,
        public ?string $deprep_starts_at = null,
        public ?string $deprep_ends_at = null,
        public ?string $ordered_at = null,
        public ?string $quote_invalid_at = null,
        // Boolean flags (C3b/C3c/C3d). Plain-nullable: null = leave unchanged, a
        // provided true/false writes that value.
        public ?bool $use_chargeable_days = null,
        public ?bool $open_ended_rental = null,
        public ?bool $customer_collecting = null,
        public ?bool $customer_returning = null,
        public ?bool $invoiced = null,
        // Clearable values use Optional so an absent key (unchanged) is told apart
        // from an explicit null (clear the column), mirroring reference/description.
        public string|null|Optional $chargeable_days = new Optional,
        public string|null|Optional $delivery_instructions = new Optional,
        public string|null|Optional $collection_instructions = new Optional,
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
            'prep_starts_at' => ['sometimes', 'nullable', 'date'],
            'prep_ends_at' => ['sometimes', 'nullable', 'date'],
            'load_starts_at' => ['sometimes', 'nullable', 'date'],
            'load_ends_at' => ['sometimes', 'nullable', 'date'],
            'deliver_starts_at' => ['sometimes', 'nullable', 'date'],
            'deliver_ends_at' => ['sometimes', 'nullable', 'date'],
            'setup_starts_at' => ['sometimes', 'nullable', 'date'],
            'setup_ends_at' => ['sometimes', 'nullable', 'date'],
            'show_starts_at' => ['sometimes', 'nullable', 'date'],
            'show_ends_at' => ['sometimes', 'nullable', 'date'],
            'takedown_starts_at' => ['sometimes', 'nullable', 'date'],
            'takedown_ends_at' => ['sometimes', 'nullable', 'date'],
            'collect_starts_at' => ['sometimes', 'nullable', 'date'],
            'collect_ends_at' => ['sometimes', 'nullable', 'date'],
            'unload_starts_at' => ['sometimes', 'nullable', 'date'],
            'unload_ends_at' => ['sometimes', 'nullable', 'date'],
            'deprep_starts_at' => ['sometimes', 'nullable', 'date'],
            'deprep_ends_at' => ['sometimes', 'nullable', 'date'],
            'ordered_at' => ['sometimes', 'nullable', 'date'],
            'quote_invalid_at' => ['sometimes', 'nullable', 'date'],
            'use_chargeable_days' => ['sometimes', 'boolean'],
            'chargeable_days' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'open_ended_rental' => ['sometimes', 'boolean'],
            'customer_collecting' => ['sometimes', 'boolean'],
            'customer_returning' => ['sometimes', 'boolean'],
            'invoiced' => ['sometimes', 'boolean'],
            'delivery_instructions' => ['sometimes', 'nullable', 'string'],
            'collection_instructions' => ['sometimes', 'nullable', 'string'],
            'tag_list' => ['sometimes', 'nullable', 'array'],
            'tag_list.*' => ['string', 'max:255'],
            'custom_fields' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
