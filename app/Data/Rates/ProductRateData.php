<?php

namespace App\Data\Rates;

use App\Data\Concerns\EntityReferenceData;
use App\Data\Concerns\FormatsTimestamps;
use App\Enums\RateTransactionType;
use App\Models\ProductRate;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class ProductRateData extends Data
{
    use FormatsTimestamps;

    public function __construct(
        public int $id,
        public int $product_id,
        public int $rate_definition_id,
        public ?int $store_id,
        public string $transaction_type,
        public string $transaction_type_name,
        public string $price,
        public string $currency,
        public ?string $valid_from,
        public ?string $valid_to,
        public int $priority,
        public string $created_at,
        public string $updated_at,
        public ?EntityReferenceData $rate_definition = null,
    ) {}

    public static function fromModel(ProductRate $rate): self
    {
        /** @var RateTransactionType $type */
        $type = $rate->transaction_type;

        /** @var Carbon $createdAt */
        $createdAt = $rate->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $rate->updated_at;

        return new self(
            id: $rate->id,
            product_id: $rate->product_id,
            rate_definition_id: $rate->rate_definition_id,
            store_id: $rate->store_id,
            transaction_type: $type->value,
            transaction_type_name: $type->label(),
            price: $rate->formatMoneyCost('price'),
            currency: $rate->currency,
            valid_from: $rate->valid_from?->toDateString(),
            valid_to: $rate->valid_to?->toDateString(),
            priority: $rate->priority,
            created_at: self::formatTimestamp($createdAt),
            updated_at: self::formatTimestamp($updatedAt),
            rate_definition: $rate->relationLoaded('rateDefinition') && $rate->rateDefinition
                ? EntityReferenceData::from(['id' => $rate->rateDefinition->id, 'name' => $rate->rateDefinition->name])
                : null,
        );
    }
}
