<?php

namespace Database\Factories;

use App\Models\AvailabilitySnapshot;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<AvailabilitySnapshot>
 */
class AvailabilitySnapshotFactory extends Factory
{
    protected $model = AvailabilitySnapshot::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalStock = fake()->numberBetween(1, 20);
        $totalDemanded = fake()->numberBetween(0, $totalStock);

        return [
            'product_id' => Product::factory(),
            'store_id' => Store::factory(),
            'slot_start' => Carbon::today('UTC'),
            'total_stock' => $totalStock,
            'total_demanded' => $totalDemanded,
            'available' => $totalStock - $totalDemanded,
            'demand_breakdown' => $totalDemanded > 0 ? ['opportunity_item' => $totalDemanded] : [],
            'pending_checkin_quantity' => 0,
            'calculated_at' => Carbon::now('UTC'),
        ];
    }

    /**
     * A snapshot for a specific slot with explicit stock/demand figures.
     */
    public function slot(Carbon $slotStart, int $totalStock, int $totalDemanded): static
    {
        return $this->state(fn (): array => [
            'slot_start' => $slotStart,
            'total_stock' => $totalStock,
            'total_demanded' => $totalDemanded,
            'available' => $totalStock - $totalDemanded,
        ]);
    }
}
