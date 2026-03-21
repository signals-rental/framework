<?php

namespace Database\Factories;

use App\Models\CostGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CostGroup>
 */
class CostGroupFactory extends Factory
{
    protected $model = CostGroup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
