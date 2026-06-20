<?php

namespace App\Data\Opportunities;

use App\Actions\Opportunities\AddOpportunityItem;
use App\Data\Casts\MoneyInput;
use App\Enums\ChargePeriod;
use App\Enums\LineItemTransactionType;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;

/**
 * Input DTO for adding a line item to an opportunity.
 *
 * `unit_price` is an OPTIONAL manual override (minor units; accepts an int =
 * already-minor or a decimal string/float = major units against `currency`). When
 * omitted the rate engine prices the line. `currency` is the sibling scale for the
 * {@see MoneyInput} cast.
 */
class AddOpportunityItemData extends Data
{
    public function __construct(
        public string $name,
        public ?int $item_id = null,
        public ?string $item_type = null,
        public ?string $description = null,
        public string $quantity = '1',
        public int $transaction_type = LineItemTransactionType::Rental->value,
        public int $charge_period = ChargePeriod::Day->value,
        public ?string $starts_at = null,
        public ?string $ends_at = null,
        public bool $is_optional = false,
        public ?string $discount_percent = null,
        /**
         * The 0-based display position. When null (the default for a fresh add) the
         * {@see AddOpportunityItem} action appends the line
         * after the opportunity's existing items; an explicit value (e.g. the
         * clone/version paths preserving the source order) is honoured as-is.
         */
        public ?int $sort_order = null,
        public ?string $notes = null,
        /** @var array<string, mixed>|null */
        public ?array $custom_fields = null,
        public string $currency = 'GBP',
        #[WithCast(MoneyInput::class)]
        public ?int $unit_price = null,
        /**
         * Internal: the quote version scope the new line belongs to. Not a public
         * input field — it is set by the version-clone path ({@see VersionCreated}).
         * When null the action resolves it from the opportunity's active version.
         */
        public ?int $version_id = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'item_id' => ['sometimes', 'nullable', 'integer'],
            'item_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'quantity' => ['sometimes', 'numeric', 'min:0'],
            // Sub-rental is a Phase 4 deliverable with no creation path yet, so it
            // is rejected at the input boundary (its totals bucket is stubbed 0).
            // A closure carries the Phase-4 message inline so it surfaces whether
            // the rules run via the controller's $request->validate() or the DTO's
            // own validateAndCreate() — neither consults the messages() method for
            // this attribute here.
            'transaction_type' => [
                'sometimes',
                'integer',
                Rule::enum(LineItemTransactionType::class),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ((int) $value === LineItemTransactionType::SubRental->value) {
                        $fail('Sub-rental line items are not available until Phase 4.');
                    }
                },
            ],
            'charge_period' => ['sometimes', 'integer', new Enum(ChargePeriod::class)],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
            'is_optional' => ['sometimes', 'boolean'],
            'discount_percent' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'custom_fields' => ['sometimes', 'nullable', 'array'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'unit_price' => ['sometimes', 'nullable', 'numeric'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'transaction_type.enum' => 'The selected transaction type is invalid.',
            'charge_period.enum' => 'The selected charge period is invalid.',
        ];
    }
}
