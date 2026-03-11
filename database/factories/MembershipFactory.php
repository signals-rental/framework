<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\Membership;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Membership>
 */
class MembershipFactory extends Factory
{
    protected $model = Membership::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'is_owner' => false,
            'is_admin' => false,
            'is_active' => true,
        ];
    }

    public function owner(): static
    {
        return $this->state(fn () => ['is_owner' => true]);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['is_admin' => true]);
    }

    public function forStore(?Store $store = null): static
    {
        return $this->state(fn () => [
            'store_id' => $store !== null ? $store->id : Store::factory(),
        ]);
    }
}
