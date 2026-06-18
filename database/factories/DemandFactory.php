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
            // Default to a zero-buffer demand: buffered bounds equal the raw
            // dates. Use buffered() / window() with explicit buffers to widen.
            'buffered_starts_at' => $startsAt,
            'buffered_ends_at' => $endsAt,
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
     * Set the demand window explicitly (zero-buffer), recomputing the buffered
     * bounds and the PostgreSQL `period` to match the raw dates.
     */
    public function window(Carbon $startsAt, Carbon $endsAt): static
    {
        return $this->state(fn (): array => [
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'buffered_starts_at' => $startsAt,
            'buffered_ends_at' => $endsAt,
            ...$this->periodAttribute($startsAt, $endsAt),
        ]);
    }

    /**
     * Set the raw window AND a wider buffered window (prep/turnaround baked in).
     *
     * The raw `starts_at` / `ends_at` keep the pre-buffer dates while
     * `buffered_starts_at` / `buffered_ends_at` (and, on Postgres, the `period`
     * tstzrange) carry the buffered window the overlap logic actually queries.
     */
    public function buffered(Carbon $startsAt, Carbon $endsAt, Carbon $bufferedStart, Carbon $bufferedEnd): static
    {
        return $this->state(fn (): array => [
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'buffered_starts_at' => $bufferedStart,
            'buffered_ends_at' => $bufferedEnd,
            ...$this->periodAttribute($bufferedStart, $bufferedEnd),
        ]);
    }

    /**
     * The `period` column attribute, included only on PostgreSQL (the SQLite
     * test schema has no `period` column). Built from the BUFFERED window so the
     * native `period &&` overlap matches the PHP/SQLite buffered-bounds path.
     *
     * @return array<string, mixed>
     */
    protected function periodAttribute(Carbon $bufferedStart, Carbon $bufferedEnd): array
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return [];
        }

        return ['period' => Demand::periodExpression($bufferedStart, $bufferedEnd)];
    }
}
