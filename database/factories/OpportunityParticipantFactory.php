<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\Opportunity;
use App\Models\OpportunityParticipant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OpportunityParticipant>
 */
class OpportunityParticipantFactory extends Factory
{
    protected $model = OpportunityParticipant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'opportunity_id' => Opportunity::factory(),
            'member_id' => Member::factory()->contact(),
            'role' => $this->faker->randomElement([
                'Primary contact',
                'Secondary contact',
                'Account manager',
                'Site contact',
            ]),
            'mute' => false,
        ];
    }

    /**
     * The participant is muted (opted out of opportunity notifications).
     */
    public function muted(): static
    {
        return $this->state(fn (): array => ['mute' => true]);
    }
}
