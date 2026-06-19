<?php

namespace Database\Factories;

use App\Enums\ContainerAvailabilityMode;
use App\Enums\ContainerScanMode;
use App\Enums\ContainerStatus;
use App\Models\Container;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Container>
 */
class ContainerFactory extends Factory
{
    protected $model = Container::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $store = Store::factory();

        return [
            'uuid' => (string) Str::uuid(),
            'name' => fake()->words(2, true).' Case',
            'serialised_item_id' => StockLevel::factory()->serialised(),
            'product_id' => Product::factory()->containerable(),
            'is_temporary' => false,
            'barcode' => fake()->unique()->numerify('CASE-####'),
            'store_id' => $store,
            'scan_mode' => ContainerScanMode::Strict->value,
            'status' => ContainerStatus::Open->value,
        ];
    }

    /**
     * A container backed by a kit-mode containerable product.
     */
    public function kit(): static
    {
        return $this->state(fn (): array => [
            'product_id' => Product::factory()->containerable(ContainerAvailabilityMode::Kit),
        ]);
    }

    /**
     * A container backed by a hybrid-mode containerable product.
     */
    public function hybrid(): static
    {
        return $this->state(fn (): array => [
            'product_id' => Product::factory()->containerable(ContainerAvailabilityMode::Hybrid),
        ]);
    }

    /**
     * A container backed by a transport-mode containerable product.
     */
    public function transport(): static
    {
        return $this->state(fn (): array => [
            'product_id' => Product::factory()->containerable(ContainerAvailabilityMode::Transport),
        ]);
    }

    public function sealed(): static
    {
        return $this->state(fn (): array => [
            'status' => ContainerStatus::Sealed->value,
            'sealed_at' => now(),
        ]);
    }
}
