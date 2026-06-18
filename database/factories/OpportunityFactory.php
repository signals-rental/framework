<?php

namespace Database\Factories;

use App\Enums\OpportunityState;
use App\Enums\OpportunityStatus;
use App\Models\Opportunity;
use App\Services\SequenceAllocator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Opportunity>
 */
class OpportunityFactory extends Factory
{
    protected $model = Opportunity::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // The PK is application-assigned ($incrementing = false), so allocate
            // one from the same ascending sequence the action uses.
            'id' => app(SequenceAllocator::class)->next('opportunities'),
            // Factory rows bypass the event stream, so synthesise a unique
            // snowflake-shaped state id to satisfy the unique link column.
            'state_id' => snowflake_id(),
            'subject' => fake()->unique()->words(3, true),
            'state' => OpportunityState::Draft->value,
            'status' => OpportunityState::Draft->defaultStatus()->statusValue(),
            'reference' => fake()->optional()->bothify('REF-####'),
            'starts_at' => fake()->dateTimeBetween('+1 week', '+2 weeks'),
            'ends_at' => fake()->dateTimeBetween('+3 weeks', '+4 weeks'),
        ];
    }

    public function quotation(): static
    {
        return $this->state(fn (): array => [
            'state' => OpportunityState::Quotation->value,
            'status' => OpportunityState::Quotation->defaultStatus()->statusValue(),
        ]);
    }

    public function order(): static
    {
        return $this->state(fn (): array => [
            'state' => OpportunityState::Order->value,
            'status' => OpportunityState::Order->defaultStatus()->statusValue(),
        ]);
    }

    public function reserved(): static
    {
        return $this->state(fn (): array => [
            'state' => OpportunityState::Quotation->value,
            'status' => OpportunityStatus::QuotationReserved->statusValue(),
        ]);
    }
}
