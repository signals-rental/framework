<?php

namespace App\Data\Products;

use App\Enums\TransactionType;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

class CreateStockTransactionData extends Data
{
    public function __construct(
        public int $stock_level_id,
        public ?int $store_id = null,
        public int $transaction_type = TransactionType::Buy->value,
        public ?string $transaction_at = null,
        public string $quantity = '1.0',
        public ?string $description = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'stock_level_id' => ['required', 'integer', 'exists:stock_levels,id'],
            'store_id' => ['sometimes', 'nullable', 'integer', 'exists:stores,id'],
            // The API accepts any valid transaction type (incl. system types like
            // Opening/Transfer); the inline web form separately restricts its dropdown
            // to manual types. Invalid values are still rejected.
            'transaction_type' => ['required', 'integer', Rule::in(array_column(TransactionType::cases(), 'value'))],
            'transaction_at' => ['sometimes', 'nullable', 'date'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'description' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
