<?php

namespace Database\Factories;

use App\Models\ListName;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ListName>
 */
class ListNameFactory extends Factory
{
    protected $model = ListName::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'is_system' => false,
            'is_hierarchical' => false,
        ];
    }

    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_system' => true,
        ]);
    }

    public function hierarchical(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_hierarchical' => true,
        ]);
    }
}
