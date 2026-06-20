<?php

namespace App\Data\Opportunities;

use App\Data\Casts\MoneyInput;
use App\Enums\MembershipType;
use App\Models\Member;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

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
        // Delivery/collection address FKs (C-data-2), each one of the member's addresses.
        public ?int $delivery_address_id = null,
        public ?int $collection_address_id = null,
        // Sales priority/quality rating 0–5 (C3i).
        public ?int $rating = null,
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
    public static function rules(?ValidationContext $context = null): array
    {
        // Scope the delivery/collection address to an Address owned by a Member
        // (polymorphic addressable_type/addressable_id). When the validation context
        // exposes the payload (Spatie's auto-validation path) we further constrain to
        // the payload's member_id, closing the IDOR at the DTO layer. The manual
        // `$request->validate(::rules())` path has no context, so this narrows to any
        // Member-owned address; the action then enforces the exact member match
        // authoritatively (a caller can spoof or omit member_id past this rule).
        $memberAddressExists = Rule::exists('addresses', 'id')
            ->where('addressable_type', Member::class);

        $payloadMemberId = is_array($context?->payload) ? ($context->payload['member_id'] ?? null) : null;
        if ($payloadMemberId !== null) {
            $memberAddressExists->where('addressable_id', $payloadMemberId);
        }

        return [
            'subject' => ['required', 'string', 'max:255'],
            // The opportunity customer must be an Organisation member (not a
            // Contact/User/Venue). Scoped here for early UX feedback; the action
            // re-asserts authoritatively because ::rules() is called context-free
            // on the manual validate() path.
            'member_id' => ['sometimes', 'nullable', 'integer', Rule::exists('members', 'id')
                ->where('membership_type', MembershipType::Organisation->value)
                ->withoutTrashed()],
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
            'delivery_address_id' => ['sometimes', 'nullable', 'integer', $memberAddressExists],
            'collection_address_id' => ['sometimes', 'nullable', 'integer', $memberAddressExists],
            'rating' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:5'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'prices_include_tax' => ['sometimes', 'boolean'],
            'charge_total' => ['sometimes', 'numeric'],
            'tag_list' => ['sometimes', 'nullable', 'array'],
            'tag_list.*' => ['string', 'max:255'],
            'custom_fields' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
