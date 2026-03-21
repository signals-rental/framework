<?php

namespace App\Data\Products;

use App\Data\Concerns\FormatsTimestamps;
use App\Models\Accessory;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class AccessoryData extends Data
{
    use FormatsTimestamps;

    public function __construct(
        public int $id,
        public int $product_id,
        public int $accessory_product_id,
        public string $related_name,
        public string $quantity,
        public bool $included,
        public bool $zero_priced,
        public int $sort_order,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(Accessory $accessory): self
    {
        /** @var Carbon $createdAt */
        $createdAt = $accessory->created_at;

        /** @var Carbon $updatedAt */
        $updatedAt = $accessory->updated_at;

        $relatedName = '';
        if ($accessory->relationLoaded('accessoryProduct') && $accessory->accessoryProduct) {
            $relatedName = $accessory->accessoryProduct->name;
        }

        return new self(
            id: $accessory->id,
            product_id: $accessory->product_id,
            accessory_product_id: $accessory->accessory_product_id,
            related_name: $relatedName,
            quantity: number_format((float) $accessory->quantity, 1, '.', ''),
            included: $accessory->included,
            zero_priced: $accessory->zero_priced,
            sort_order: $accessory->sort_order,
            created_at: self::formatTimestamp($createdAt),
            updated_at: self::formatTimestamp($updatedAt),
        );
    }
}
