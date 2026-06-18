<?php

namespace App\Data\Rates;

use App\Data\Casts\MoneyInput;
use App\Enums\RateTransactionType;
use Illuminate\Validation\Rules\Enum;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * Partial update for a product rate. Each property is `Optional` so an omitted
 * key leaves the field untouched, while an explicit `null` on a nullable field
 * (store_id, valid_from, valid_to) clears it — a distinction the prior
 * all-nullable shape could not express.
 */
class UpdateProductRateData extends Data
{
    public function __construct(
        public int|Optional $rate_definition_id = new Optional,
        public RateTransactionType|Optional $transaction_type = new Optional,
        #[WithCast(MoneyInput::class)]
        public int|Optional $price = new Optional,
        public string|Optional $currency = new Optional,
        public int|null|Optional $store_id = new Optional,
        public string|null|Optional $valid_from = new Optional,
        public string|null|Optional $valid_to = new Optional,
        public int|Optional $priority = new Optional,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'rate_definition_id' => ['sometimes', 'integer', 'exists:rate_definitions,id'],
            'transaction_type' => ['sometimes', new Enum(RateTransactionType::class)],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'store_id' => ['sometimes', 'nullable', 'integer', 'exists:stores,id'],
            'valid_from' => ['sometimes', 'nullable', 'date'],
            'valid_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:valid_from'],
            'priority' => ['sometimes', 'integer'],
        ];
    }
}
