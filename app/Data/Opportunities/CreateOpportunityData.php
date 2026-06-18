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
        public string $currency = 'GBP',
        /**
         * Initial charge total in minor units. Accepts an int (already minor
         * units) or a decimal string/float (major units converted against
         * `currency`). Calculated totals are owned by later line-item chunks;
         * this seeds the header value.
         */
        #[WithCast(MoneyInput::class)]
        public int $charge_total = 0,
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
            'currency' => ['sometimes', 'string', 'size:3'],
            'charge_total' => ['sometimes', 'numeric'],
        ];
    }
}
