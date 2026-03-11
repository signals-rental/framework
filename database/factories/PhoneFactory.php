<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\Phone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Phone>
 */
class PhoneFactory extends Factory
{
    protected $model = Phone::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'phoneable_type' => Member::class,
            'phoneable_id' => Member::factory(),
            'number' => fake()->phoneNumber(),
            'is_primary' => false,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn () => ['is_primary' => true]);
    }
}
