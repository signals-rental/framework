<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockLevel>
 */
class StockLevelFactory extends Factory
{
    protected $model = StockLevel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'store_id' => Store::factory(),
            'quantity_held' => fake()->numberBetween(1, 100),
            'stock_type' => 1,
            'stock_category' => 10,
        ];
    }

    public function serialised(): static
    {
        return $this->state(fn () => [
            'stock_category' => 50,
            'quantity_held' => 1,
            'serial_number' => fake()->unique()->numerify('SN-######'),
            'asset_number' => fake()->unique()->numerify('A-####'),
            'item_name' => fake()->words(2, true),
        ]);
    }

    public function bulk(): static
    {
        return $this->state(fn () => [
            'stock_category' => 10,
            'quantity_held' => fake()->numberBetween(5, 50),
        ]);
    }

    public function allocated(): static
    {
        return $this->state(fn () => [
            'quantity_allocated' => fake()->numberBetween(1, 5),
        ]);
    }
}
