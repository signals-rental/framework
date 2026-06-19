<?php

namespace Database\Factories;

use App\Enums\ProductType;
use App\Enums\StockMethod;
use App\Models\Product;
use App\Models\ProductGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'product_type' => ProductType::Rental,
            'stock_method' => StockMethod::Bulk,
            'is_active' => true,
        ];
    }

    public function rental(): static
    {
        return $this->state(fn () => [
            'product_type' => ProductType::Rental,
        ]);
    }

    public function sale(): static
    {
        return $this->state(fn () => [
            'product_type' => ProductType::Sale,
        ]);
    }

    public function service(): static
    {
        return $this->state(fn () => [
            'product_type' => ProductType::Service,
        ]);
    }

    public function serialised(): static
    {
        return $this->state(fn () => [
            'stock_method' => StockMethod::Serialised,
        ]);
    }

    public function bulk(): static
    {
        return $this->state(fn () => [
            'stock_method' => StockMethod::Bulk,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }

    /**
     * A product excluded from the availability engine.
     */
    public function notTracked(): static
    {
        return $this->state(fn () => [
            'track_availability' => false,
        ]);
    }

    /**
     * A catalogue kit: composed read-time from components, so it generates no
     * demand of its own and holds no snapshot rows. Attach components via the
     * `SerialisedComponent` factory.
     */
    public function kit(): static
    {
        return $this->state(fn () => [
            'is_kit' => true,
            'track_availability' => false,
        ]);
    }

    public function withGroup(): static
    {
        return $this->state(fn () => [
            'product_group_id' => ProductGroup::factory(),
        ]);
    }
}
