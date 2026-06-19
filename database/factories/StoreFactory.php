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
            'is_virtual' => false,
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

    /**
     * Enable shortage auto-resolution with an optional ordered resolver list.
     *
     * @param  list<string>|null  $preferredResolvers
     */
    public function autoResolveShortages(?array $preferredResolvers = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'shortage_auto_resolve_enabled' => true,
            'shortage_preferred_resolvers' => $preferredResolvers,
        ]);
    }

    /**
     * Flag the store as virtual (vehicle, job site, sub-hire holding location).
     */
    public function virtual(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_virtual' => true,
        ]);
    }

    /**
     * Set the store's per-day-of-week operating hours.
     *
     * @param  array<string, mixed>  $hours
     */
    public function operatingHours(array $hours): static
    {
        return $this->state(fn (array $attributes): array => [
            'operating_hours' => $hours,
        ]);
    }
}
