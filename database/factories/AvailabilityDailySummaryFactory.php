<?php

namespace Database\Factories;

use App\Models\AvailabilityDailySummary;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<AvailabilityDailySummary>
 */
class AvailabilityDailySummaryFactory extends Factory
{
    protected $model = AvailabilityDailySummary::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $minAvailable = fake()->numberBetween(0, 10);
        $maxAvailable = $minAvailable + fake()->numberBetween(0, 10);

        return [
            'product_id' => Product::factory(),
            'store_id' => Store::factory(),
            'date' => Carbon::today('UTC')->toDateString(),
            'min_available' => $minAvailable,
            'max_available' => $maxAvailable,
            'has_shortage' => $minAvailable < 0,
            'calculated_at' => Carbon::now('UTC'),
        ];
    }

    /**
     * A summary for a specific day with explicit min/max availability; the
     * shortage flag is derived from the minimum.
     */
    public function day(Carbon $date, int $minAvailable, int $maxAvailable): static
    {
        return $this->state(fn (): array => [
            'date' => $date->toDateString(),
            'min_available' => $minAvailable,
            'max_available' => $maxAvailable,
            'has_shortage' => $minAvailable < 0,
        ]);
    }
}
