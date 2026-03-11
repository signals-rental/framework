<?php

namespace Database\Factories;

use App\Models\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    protected $model = Country::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->countryCode(),
            'code3' => fake()->unique()->regexify('[A-Z]{3}'),
            'name' => fake()->country(),
            'currency_code' => fake()->currencyCode(),
            'phone_prefix' => '+'.fake()->numberBetween(1, 999),
            'default_timezone' => fake()->timezone(),
            'default_date_format' => 'd/m/Y',
            'default_time_format' => 'H:i',
            'default_number_format' => '#,##0.00',
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
