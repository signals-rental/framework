<?php

namespace Database\Factories;

use App\Models\ProductTaxClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductTaxClass>
 */
class ProductTaxClassFactory extends Factory
{
    protected $model = ProductTaxClass::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }
}
