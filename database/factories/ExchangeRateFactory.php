<?php

namespace Database\Factories;

use App\Models\ExchangeRate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

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

    /**
     * Set the currency pair and (optionally) explicit rate/inverse for the row.
     */
    public function pair(string $from, string $to, ?string $rate = null, ?string $inverse = null): static
    {
        return $this->state(function (array $attributes) use ($from, $to, $rate, $inverse): array {
            $resolvedRate = $rate ?? (string) $attributes['rate'];

            return [
                'source_currency_code' => $from,
                'target_currency_code' => $to,
                'rate' => $resolvedRate,
                'inverse_rate' => $inverse ?? (string) (1 / (float) $resolvedRate),
            ];
        });
    }

    /**
     * Mark the rate as effective from a given moment with no expiry.
     */
    public function effectiveFrom(Carbon $effectiveAt): static
    {
        return $this->state(fn (array $attributes): array => [
            'effective_at' => $effectiveAt,
            'expires_at' => null,
        ]);
    }

    /**
     * Mark the rate as effective within a bounded window.
     */
    public function effectiveBetween(Carbon $effectiveAt, Carbon $expiresAt): static
    {
        return $this->state(fn (array $attributes): array => [
            'effective_at' => $effectiveAt,
            'expires_at' => $expiresAt,
        ]);
    }
}
