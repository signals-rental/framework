<?php

namespace App\Data\Opportunities;

use App\Data\Casts\MoneyInput;
use App\Enums\ChargePeriod;
use App\Enums\LineItemTransactionType;
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
        public int $sort_order = 0,
        public ?string $notes = null,
        /** @var array<string, mixed>|null */
        public ?array $custom_fields = null,
        public string $currency = 'GBP',
        #[WithCast(MoneyInput::class)]
        public ?int $unit_price = null,
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
            'transaction_type' => ['sometimes', 'integer', new Enum(LineItemTransactionType::class)],
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
