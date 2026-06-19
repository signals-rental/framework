<?php

namespace Database\Factories;

use App\Models\Container;
use App\Models\ContainerItem;
use App\Models\Product;
use App\Models\StockLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContainerItem>
 */
class ContainerItemFactory extends Factory
{
    protected $model = ContainerItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'container_id' => Container::factory(),
            'serialised_item_id' => StockLevel::factory()->serialised(),
            'product_id' => Product::factory()->serialised(),
            'packed_at' => now(),
            'packed_by_user_id' => null,
            'unpacked_at' => null,
        ];
    }
}
