<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Setting>
 */
class SettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group' => 'test',
            'key' => fake()->unique()->word(),
            'value' => fake()->sentence(),
            'type' => 'string',
        ];
    }

    /**
     * Indicate the setting stores a boolean value.
     */
    public function boolean(bool $value = true): static
    {
        return $this->state(fn (array $attributes) => [
            'value' => $value ? 'true' : 'false',
            'type' => 'boolean',
        ]);
    }

    /**
     * Indicate the setting stores an integer value.
     */
    public function integer(int $value = 0): static
    {
        return $this->state(fn (array $attributes) => [
            'value' => (string) $value,
            'type' => 'integer',
        ]);
    }

    /**
     * Indicate the setting stores a JSON value.
     *
     * @param  array<string, mixed>  $value
     */
    public function json(array $value = []): static
    {
        return $this->state(fn (array $attributes) => [
            'value' => json_encode($value),
            'type' => 'json',
        ]);
    }
}
