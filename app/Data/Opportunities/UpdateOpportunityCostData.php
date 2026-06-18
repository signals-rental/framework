<?php

namespace App\Data\Opportunities;

use App\Data\Casts\MoneyInput;
use App\Enums\LineItemTransactionType;
use App\Enums\OpportunityCostType;
use Illuminate\Validation\Rules\Enum;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * Input DTO for updating an opportunity cost.
 *
 * Every field is OPTIONAL — the UpdateOpportunityCost action merges only the
 * provided fields over the cost's current values before firing CostUpdated, so an
 * omitted field is left untouched. `amount` is the per-unit charge in minor units
 * (int = already-minor, decimal string/float = major units against `currency`).
 */
class UpdateOpportunityCostData extends Data
{
    public function __construct(
        public string|Optional $description,
        public int|Optional $cost_type,
        public int|Optional $transaction_type,
        public string|Optional $quantity,
        public bool|Optional $is_optional,
        public int|Optional $sort_order,
        public string|null|Optional $notes,
        public string $currency = 'GBP',
        #[WithCast(MoneyInput::class)]
        public int|Optional $amount = new Optional,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'description' => ['sometimes', 'string', 'max:255'],
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
