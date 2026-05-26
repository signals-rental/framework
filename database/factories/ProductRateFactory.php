<?php

namespace Database\Factories;

use App\Enums\RateTransactionType;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\RateDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductRate>
 */
class ProductRateFactory extends Factory
{
    protected $model = ProductRate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'rate_definition_id' => RateDefinition::factory(),
            'store_id' => null,
            'transaction_type' => RateTransactionType::Rental,
            'price' => fake()->numberBetween(1000, 50000),
            'currency' => 'GBP',
            'valid_from' => null,
            'valid_to' => null,
            'priority' => 0,
        ];
    }

    public function forSale(): static
    {
        return $this->state(fn (): array => [
            'transaction_type' => RateTransactionType::Sale,
        ]);
    }

    public function withPriority(int $priority): static
    {
        return $this->state(fn (): array => [
            'priority' => $priority,
        ]);
    }
}
