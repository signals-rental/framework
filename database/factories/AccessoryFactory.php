<?php

namespace Database\Factories;

use App\Models\Accessory;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Accessory>
 */
class AccessoryFactory extends Factory
{
    protected $model = Accessory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'accessory_product_id' => Product::factory(),
            'quantity' => 1,
            'included' => true,
            'zero_priced' => true,
            'sort_order' => 0,
        ];
    }

    public function optional(): static
    {
        return $this->state(fn () => [
            'included' => false,
        ]);
    }

    public function priced(): static
    {
        return $this->state(fn () => [
            'zero_priced' => false,
        ]);
    }
}
