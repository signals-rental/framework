<?php

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\StockLevel;
use App\Models\StockTransaction;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockTransaction>
 */
class StockTransactionFactory extends Factory
{
    protected $model = StockTransaction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stock_level_id' => StockLevel::factory(),
            'store_id' => Store::factory(),
            'transaction_type' => TransactionType::Opening,
            'transaction_at' => now(),
            'quantity' => '1.0',
            'manual' => true,
        ];
    }

    public function buy(): static
    {
        return $this->state(fn () => [
            'transaction_type' => TransactionType::Buy,
        ]);
    }

    public function sell(): static
    {
        return $this->state(fn () => [
            'transaction_type' => TransactionType::Sell,
        ]);
    }

    public function opening(): static
    {
        return $this->state(fn () => [
            'transaction_type' => TransactionType::Opening,
            'manual' => false,
        ]);
    }
}
