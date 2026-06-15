<?php

namespace Database\Factories;

use App\Models\MagicLinkToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MagicLinkToken>
 */
class MagicLinkTokenFactory extends Factory
{
    protected $model = MagicLinkToken::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'token_hash' => hash('sha256', fake()->unique()->sha256()),
            'expires_at' => now()->addMinutes(15),
            'consumed_at' => null,
        ];
    }

    /**
     * Indicate the token's expiry is in the past.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subMinute(),
        ]);
    }

    /**
     * Indicate the token has already been consumed.
     */
    public function consumed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'consumed_at' => now(),
        ]);
    }
}
