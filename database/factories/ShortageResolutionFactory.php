<?php

namespace Database\Factories;

use App\Enums\ShortageResolutionStatus;
use App\Enums\ShortageResolutionType;
use App\Models\ShortageResolution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShortageResolution>
 */
class ShortageResolutionFactory extends Factory
{
    protected $model = ShortageResolution::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'resolver_key' => 'partial',
            'resolution_type' => ShortageResolutionType::Partial->value,
            'status' => ShortageResolutionStatus::Confirmed->value,
            'quantity_resolved' => fake()->numberBetween(1, 5),
            'cost' => null,
            'metadata' => [],
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => ShortageResolutionStatus::Pending->value,
        ]);
    }

    public function monitoring(): static
    {
        return $this->state(fn (): array => [
            'resolver_key' => 'waitlist',
            'resolution_type' => ShortageResolutionType::Waitlist->value,
            'status' => ShortageResolutionStatus::Monitoring->value,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => ShortageResolutionStatus::Cancelled->value,
            'cancelled_at' => now(),
        ]);
    }

    /**
     * Set the resolver key and resolution type together.
     */
    public function ofType(string $resolverKey, ShortageResolutionType $type): static
    {
        return $this->state(fn (): array => [
            'resolver_key' => $resolverKey,
            'resolution_type' => $type->value,
        ]);
    }
}
