<?php

namespace Database\Factories;

use App\Models\ProductGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductGroup>
 */
class ProductGroupFactory extends Factory
{
    protected $model = ProductGroup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'parent_id' => null,
            'sort_order' => 0,
        ];
    }

    public function withParent(): static
    {
        return $this->state(fn () => [
            'parent_id' => ProductGroup::factory(),
        ]);
    }
}
