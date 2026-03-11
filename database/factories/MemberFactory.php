<?php

namespace Database\Factories;

use App\Enums\MembershipType;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    protected $model = Member::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'membership_type' => MembershipType::Contact,
            'is_active' => true,
        ];
    }

    public function organisation(): static
    {
        return $this->state(fn () => [
            'name' => fake()->company(),
            'membership_type' => MembershipType::Organisation,
        ]);
    }

    public function contact(): static
    {
        return $this->state(fn () => [
            'name' => fake()->name(),
            'membership_type' => MembershipType::Contact,
        ]);
    }

    public function venue(): static
    {
        return $this->state(fn () => [
            'name' => fake()->company().' Venue',
            'membership_type' => MembershipType::Venue,
        ]);
    }

    public function user(): static
    {
        return $this->state(fn () => [
            'name' => fake()->name(),
            'membership_type' => MembershipType::User,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function withMembership(): static
    {
        return $this->has(\App\Models\Membership::factory(), 'memberships');
    }
}
