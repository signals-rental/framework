<?php

namespace App\Data\Opportunities;

use App\Data\Casts\MoneyInput;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;

class CreateOpportunityData extends Data
{
    public function __construct(
        public string $subject,
        public ?int $member_id = null,
        public ?int $store_id = null,
        public ?int $owned_by = null,
        public ?int $venue_id = null,
        public ?string $reference = null,
        public ?string $description = null,
        public ?string $external_description = null,
        public ?string $starts_at = null,
        public ?string $ends_at = null,
        public ?string $charge_starts_at = null,
        public ?string $charge_ends_at = null,
        // Event-logistics lifecycle dates (C3a).
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
        // Chargeable-days + open-ended-rental controls (C3b).
        public bool $use_chargeable_days = false,
        public ?string $chargeable_days = null,
        public bool $open_ended_rental = false,
        // Customer collecting/returning flags (C3c).
        public bool $customer_collecting = false,
        public bool $customer_returning = false,
        public ?string $delivery_instructions = null,
        public ?string $collection_instructions = null,
        public string $currency = 'GBP',
        public bool $prices_include_tax = false,
        /**
         * Initial charge total in minor units. Accepts an int (already minor
         * units) or a decimal string/float (major units converted against
         * `currency`). Calculated totals are owned by later line-item chunks;
         * this seeds the header value.
         */
        #[WithCast(MoneyInput::class)]
        public int $charge_total = 0,
        /** @var array<int, string>|null */
        public ?array $tag_list = null,
        /** @var array<string, mixed>|null */
        public ?array $custom_fields = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
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
            'delivery_instructions' => ['sometimes', 'nullable', 'string'],
            'collection_instructions' => ['sometimes', 'nullable', 'string'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'prices_include_tax' => ['sometimes', 'boolean'],
            'charge_total' => ['sometimes', 'numeric'],
            'tag_list' => ['sometimes', 'nullable', 'array'],
            'tag_list.*' => ['string', 'max:255'],
            'custom_fields' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
