<?php

namespace App\Data\Opportunities;

use App\Data\Casts\MoneyInput;
use App\Enums\LineItemTransactionType;
use App\Enums\OpportunityCostType;
use Illuminate\Validation\Rules\Enum;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;

/**
 * Input DTO for adding an ad-hoc cost to an opportunity.
 *
 * `amount` is the per-unit charge (minor units; accepts an int = already-minor or
 * a decimal string/float = major units against `currency`). Costs are NOT priced
 * by the rate engine. `currency` is the sibling scale for the {@see MoneyInput}
 * cast.
 */
class AddOpportunityCostData extends Data
{
    public function __construct(
        public string $description,
        public int $cost_type = OpportunityCostType::Misc->value,
        public int $transaction_type = LineItemTransactionType::Service->value,
        public string $quantity = '1',
        public bool $is_optional = false,
        public int $sort_order = 0,
        public ?string $notes = null,
        public string $currency = 'GBP',
        #[WithCast(MoneyInput::class)]
        public int $amount = 0,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:255'],
            'cost_type' => ['sometimes', 'integer', new Enum(OpportunityCostType::class)],
            'transaction_type' => ['sometimes', 'integer', new Enum(LineItemTransactionType::class)],
            'quantity' => ['sometimes', 'numeric', 'min:0'],
            'is_optional' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'amount' => ['sometimes', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'cost_type.enum' => 'The selected cost type is invalid.',
            'transaction_type.enum' => 'The selected transaction type is invalid.',
        ];
    }
}
