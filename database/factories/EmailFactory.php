<?php

namespace Database\Factories;

use App\Models\Email;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Email>
 */
class EmailFactory extends Factory
{
    protected $model = Email::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'emailable_type' => Member::class,
            'emailable_id' => Member::factory(),
            'address' => fake()->unique()->safeEmail(),
            'is_primary' => false,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn () => ['is_primary' => true]);
    }
}
