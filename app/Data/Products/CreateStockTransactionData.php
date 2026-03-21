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
        public int $transaction_type = 4,
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
            'transaction_type' => ['required', 'integer', Rule::in(TransactionType::manualCreationValues())],
            'transaction_at' => ['sometimes', 'nullable', 'date'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'description' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
