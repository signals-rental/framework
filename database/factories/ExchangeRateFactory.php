<?php

namespace Database\Factories;

use App\Models\ExchangeRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExchangeRate>
 */
class ExchangeRateFactory extends Factory
{
    protected $model = ExchangeRate::class;

    public function definition(): array
    {
        $rate = fake()->randomFloat(8, 0.5, 2.0);

        return [
            'source_currency_code' => 'GBP',
            'target_currency_code' => 'USD',
            'rate' => $rate,
            'inverse_rate' => 1 / $rate,
            'source' => 'manual',
            'effective_at' => now(),
            'expires_at' => null,
        ];
    }
}
