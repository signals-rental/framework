<?php

namespace Database\Factories;

use App\Models\OpportunitySection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OpportunitySection>
 */
class OpportunitySectionFactory extends Factory
{
    protected $model = OpportunitySection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Parent opportunity — caller may override via for()/state().
            'opportunity_id' => OpportunityFactory::new(),
            'name' => fake()->words(2, true),
            'sort_order' => fake()->numberBetween(0, 20),
        ];
    }
}
