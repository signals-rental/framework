<?php

namespace Database\Factories;

use App\Models\OAuthIdentity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OAuthIdentity>
 */
class OAuthIdentityFactory extends Factory
{
    protected $model = OAuthIdentity::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => fake()->randomElement(['google', 'microsoft']),
            'provider_id' => (string) fake()->unique()->numerify('################'),
            'email' => fake()->unique()->safeEmail(),
        ];
    }

    /**
     * Indicate the identity is a Google account.
     */
    public function google(): static
    {
        return $this->state(fn (array $attributes): array => [
            'provider' => 'google',
        ]);
    }

    /**
     * Indicate the identity is a Microsoft 365 account.
     */
    public function microsoft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'provider' => 'microsoft',
        ]);
    }
}
