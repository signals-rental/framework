<?php

namespace Database\Factories;

use App\Enums\ShortagePolicy;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'street' => fake()->streetAddress(),
            'city' => fake()->city(),
            'county' => fake()->city(),
            'postcode' => fake()->postcode(),
            'country_code' => fake()->countryCode(),
            'is_default' => false,
        ];
    }

    /**
     * Indicate the store is the default.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    /**
     * Set the store's shortage confirmation-gate policy.
     */
    public function shortagePolicy(ShortagePolicy $policy): static
    {
        return $this->state(fn (array $attributes): array => [
            'shortage_policy' => $policy->value,
        ]);
    }
}
