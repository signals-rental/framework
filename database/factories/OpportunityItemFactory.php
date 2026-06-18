<?php

namespace Database\Factories;

use App\Enums\ChargePeriod;
use App\Enums\LineItemTransactionType;
use App\Models\OpportunityItem;
use App\Services\SequenceAllocator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OpportunityItem>
 */
class OpportunityItemFactory extends Factory
{
    protected $model = OpportunityItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 5);
        $unitPrice = fake()->numberBetween(1000, 50000);

        return [
            // The PK is application-assigned ($incrementing = false), so allocate
            // one from the same ascending sequence the M3 item event will use.
            'id' => app(SequenceAllocator::class)->next('opportunity_items'),
            // Factory rows bypass the event stream, so synthesise a unique
            // snowflake-shaped state id to satisfy the unique link column.
            'state_id' => snowflake_id(),
            // Parent opportunity — caller may override via for()/state().
            'opportunity_id' => OpportunityFactory::new(),
            'item_id' => null,
            'item_type' => null,
            'name' => fake()->words(3, true),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'charge_period' => ChargePeriod::Day->value,
            'total' => $quantity * $unitPrice,
            'transaction_type' => LineItemTransactionType::Rental->value,
            'sort_order' => fake()->numberBetween(0, 20),
            'is_optional' => false,
        ];
    }

    public function sale(): static
    {
        return $this->state(fn (): array => [
            'transaction_type' => LineItemTransactionType::Sale->value,
            'charge_period' => ChargePeriod::Fixed->value,
        ]);
    }

    public function optional(): static
    {
        return $this->state(fn (): array => [
            'is_optional' => true,
        ]);
    }
}
