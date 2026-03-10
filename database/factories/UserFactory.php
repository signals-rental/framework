<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate the user is an owner.
     */
    public function owner(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_owner' => true,
            'is_admin' => true,
        ]);
    }

    /**
     * Indicate the user is an admin.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => true,
        ]);
    }

    /**
     * Indicate the user is deactivated.
     */
    public function deactivated(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'deactivated_at' => now(),
        ]);
    }

    /**
     * Indicate the user was invited and has not yet accepted.
     */
    public function invited(): static
    {
        return $this->state(fn (array $attributes) => [
            'password' => null,
            'invited_at' => now(),
            'invitation_accepted_at' => null,
        ]);
    }

    /**
     * Indicate the user has two-factor authentication fully enabled.
     */
    public function withTwoFactor(): static
    {
        return $this->afterCreating(function (User $user) {
            $secret = app(Google2FA::class)->generateSecretKey();
            $recoveryCodes = Collection::times(8, fn () => Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4)))->toJson();

            $user->forceFill([
                'two_factor_secret' => $secret,
                'two_factor_recovery_codes' => $recoveryCodes,
            ])->save();
        });
    }
}
