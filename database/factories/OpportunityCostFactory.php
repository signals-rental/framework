<?php

namespace Database\Factories;

use App\Enums\LineItemTransactionType;
use App\Enums\OpportunityCostType;
use App\Models\OpportunityCost;
use App\Services\SequenceAllocator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OpportunityCost>
 */
class OpportunityCostFactory extends Factory
{
    protected $model = OpportunityCost::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // The PK is application-assigned ($incrementing = false), so allocate
            // one from the same ascending sequence the M3-2 cost event will use.
            'id' => app(SequenceAllocator::class)->next('opportunity_costs'),
            // Factory rows bypass the event stream, so synthesise a unique
            // snowflake-shaped state id to satisfy the unique link column.
            'state_id' => snowflake_id(),
            // Parent opportunity — caller may override via for()/state().
            'opportunity_id' => OpportunityFactory::new(),
            'description' => fake()->words(3, true),
            'cost_type' => OpportunityCostType::Misc->value,
            'transaction_type' => LineItemTransactionType::Service->value,
            'amount' => fake()->numberBetween(1000, 50000),
            'quantity' => 1,
            'is_optional' => false,
            'sort_order' => fake()->numberBetween(0, 20),
        ];
    }

    public function delivery(): static
    {
        return $this->state(fn (): array => [
            'cost_type' => OpportunityCostType::Delivery->value,
            'description' => 'Delivery',
        ]);
    }

    public function lossDamage(): static
    {
        return $this->state(fn (): array => [
            'cost_type' => OpportunityCostType::LossDamage->value,
            'description' => 'Loss / Damage Waiver',
        ]);
    }

    public function optional(): static
    {
        return $this->state(fn (): array => [
            'is_optional' => true,
        ]);
    }
}
