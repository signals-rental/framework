<?php

namespace Database\Factories;

use App\Enums\DemandPhase;
use App\Models\Demand;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<Demand>
 */
class DemandFactory extends Factory
{
    protected $model = Demand::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = Carbon::parse(fake()->dateTimeBetween('+1 week', '+2 weeks'));
        $endsAt = $startsAt->copy()->addDays(fake()->numberBetween(1, 5));
        $phase = DemandPhase::Committed;

        return [
            'product_id' => Product::factory(),
            'store_id' => Store::factory(),
            'asset_id' => null,
            'quantity' => fake()->numberBetween(1, 5),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'source_type' => 'opportunity_item',
            'source_id' => fake()->numberBetween(1, 1000),
            'phase' => $phase->value,
            'is_active' => $phase->isActive(),
            'priority' => 0,
            'metadata' => [],
            ...$this->periodAttribute($startsAt, $endsAt),
        ];
    }

    /**
     * A serialised, single-unit demand tied to a specific asset.
     */
    public function serialised(): static
    {
        return $this->state(fn (): array => [
            'quantity' => 1,
        ]);
    }

    /**
     * A demand in the given phase, with `is_active` kept consistent.
     */
    public function phase(DemandPhase $phase): static
    {
        return $this->state(fn (): array => [
            'phase' => $phase->value,
            'is_active' => $phase->isActive(),
        ]);
    }

    /**
     * Set the demand window explicitly, recomputing the PostgreSQL `period`.
     */
    public function window(Carbon $startsAt, Carbon $endsAt): static
    {
        return $this->state(fn (): array => [
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            ...$this->periodAttribute($startsAt, $endsAt),
        ]);
    }

    /**
     * The `period` column attribute, included only on PostgreSQL (the SQLite
     * test schema has no `period` column).
     *
     * @return array<string, mixed>
     */
    protected function periodAttribute(Carbon $startsAt, Carbon $endsAt): array
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return [];
        }

        return ['period' => Demand::periodExpression($startsAt, $endsAt)];
    }
}
