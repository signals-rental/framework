<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    protected $model = Address::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'addressable_type' => Member::class,
            'addressable_id' => Member::factory(),
            'name' => 'Main Office',
            'street' => fake()->streetAddress(),
            'city' => fake()->city(),
            'county' => fake()->word(),
            'postcode' => fake()->postcode(),
            'is_primary' => false,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn () => ['is_primary' => true]);
    }
}
