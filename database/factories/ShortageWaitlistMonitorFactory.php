<?php

namespace Database\Factories;

use App\Enums\WaitlistMonitorStatus;
use App\Models\Product;
use App\Models\ShortageResolution;
use App\Models\ShortageWaitlistMonitor;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShortageWaitlistMonitor>
 */
class ShortageWaitlistMonitorFactory extends Factory
{
    protected $model = ShortageWaitlistMonitor::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shortage_resolution_id' => ShortageResolution::factory()->monitoring(),
            'opportunity_item_id' => null,
            'product_id' => Product::factory(),
            'store_id' => Store::factory(),
            'quantity_needed' => fake()->numberBetween(1, 5),
            'starts_at' => now(),
            'ends_at' => now()->addDays(3),
            'status' => WaitlistMonitorStatus::Active->value,
            'expires_at' => now()->addDays(7),
        ];
    }

    public function matched(): static
    {
        return $this->state(fn (): array => [
            'status' => WaitlistMonitorStatus::Matched->value,
            'matched_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'status' => WaitlistMonitorStatus::Expired->value,
            'expires_at' => now()->subDay(),
        ]);
    }
}
