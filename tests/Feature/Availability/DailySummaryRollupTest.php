<?php

use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Enums\AvailabilityResolution;
use App\Enums\DemandPhase;
use App\Models\AvailabilityDailySummary;
use App\Models\Demand;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Store;
use App\Services\Availability\RecalculationPipeline;
use Illuminate\Support\Carbon;

/**
 * Binds a fixed availability resolution for the test body.
 */
function useResolution(AvailabilityResolution $resolution): void
{
    app()->bind(AvailabilityResolutionProvider::class, fn () => new class($resolution) implements AvailabilityResolutionProvider
    {
        public function __construct(private AvailabilityResolution $resolution) {}

        public function resolve(): AvailabilityResolution
        {
            return $this->resolution;
        }
    });
}

beforeEach(function () {
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

describe('daily summary rollup', function () {
    it('rolls half-daily slots into a single day with the worst and best availability', function () {
        useResolution(AvailabilityResolution::HalfDaily);
        $pipeline = app(RecalculationPipeline::class);

        $product = Product::factory()->bulk()->create();
        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity_held' => 10,
        ]);

        // A demand covering only the back half of 2026-05-01 (12:00–23:59) so the
        // four 6h slots of that day are NOT uniform: morning slots are fully
        // available (10) and the afternoon/evening slots are reduced (10 - 4 = 6).
        Demand::factory()
            ->phase(DemandPhase::Committed)
            ->window(Carbon::parse('2026-05-01T13:00:00Z'), Carbon::parse('2026-05-01T23:00:00Z'))
            ->create([
                'product_id' => $product->id,
                'store_id' => $this->store->id,
                'quantity' => 4,
            ]);

        $pipeline->recalculate(
            $product->id,
            $this->store->id,
            Carbon::parse('2026-05-01T00:00:00Z'),
            Carbon::parse('2026-05-02T00:00:00Z'),
        );

        $summary = AvailabilityDailySummary::query()
            ->forProductStore($product->id, $this->store->id)
            ->first();

        expect($summary)->not->toBeNull()
            // Best slot (morning, no demand) = 10; worst slot (afternoon) = 6.
            ->and($summary->max_available)->toBe(10)
            ->and($summary->min_available)->toBe(6)
            ->and($summary->has_shortage)->toBeFalse()
            ->and($summary->date->toDateString())->toBe('2026-05-01');
    });

    it('flags has_shortage when any intra-day slot goes negative under hourly resolution', function () {
        useResolution(AvailabilityResolution::Hourly);
        $pipeline = app(RecalculationPipeline::class);

        $product = Product::factory()->bulk()->create();
        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity_held' => 2,
        ]);

        // A single-hour over-demand: 09:00–10:00 demands 5 against stock of 2 → the
        // 09:00 slot is -3 while every other slot of the day is +2.
        Demand::factory()
            ->phase(DemandPhase::Committed)
            ->window(Carbon::parse('2026-05-10T09:00:00Z'), Carbon::parse('2026-05-10T10:00:00Z'))
            ->create([
                'product_id' => $product->id,
                'store_id' => $this->store->id,
                'quantity' => 5,
            ]);

        $pipeline->recalculate(
            $product->id,
            $this->store->id,
            Carbon::parse('2026-05-10T00:00:00Z'),
            Carbon::parse('2026-05-11T00:00:00Z'),
        );

        $summary = AvailabilityDailySummary::query()
            ->forProductStore($product->id, $this->store->id)
            ->first();

        expect($summary)->not->toBeNull()
            ->and($summary->min_available)->toBe(-3)
            ->and($summary->max_available)->toBe(2)
            ->and($summary->has_shortage)->toBeTrue();
    });

    it('writes one summary per day across a multi-day half-daily window', function () {
        useResolution(AvailabilityResolution::HalfDaily);
        $pipeline = app(RecalculationPipeline::class);

        $product = Product::factory()->bulk()->create();
        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity_held' => 8,
        ]);

        $pipeline->recalculate(
            $product->id,
            $this->store->id,
            Carbon::parse('2026-05-01T00:00:00Z'),
            Carbon::parse('2026-05-04T00:00:00Z'),
        );

        $summaries = AvailabilityDailySummary::query()
            ->forProductStore($product->id, $this->store->id)
            ->orderBy('date')
            ->get();

        // Three calendar days, each with no demand → full availability.
        expect($summaries)->toHaveCount(3)
            ->and($summaries->pluck('min_available')->all())->toBe([8, 8, 8])
            ->and($summaries->pluck('max_available')->all())->toBe([8, 8, 8]);
    });

    it('writes a 1:1 daily summary under daily resolution', function () {
        useResolution(AvailabilityResolution::Daily);
        $pipeline = app(RecalculationPipeline::class);

        $product = Product::factory()->bulk()->create();
        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity_held' => 6,
        ]);

        Demand::factory()
            ->phase(DemandPhase::Committed)
            ->window(Carbon::parse('2026-05-01T00:00:00Z'), Carbon::parse('2026-05-02T00:00:00Z'))
            ->create([
                'product_id' => $product->id,
                'store_id' => $this->store->id,
                'quantity' => 2,
            ]);

        $pipeline->recalculate(
            $product->id,
            $this->store->id,
            Carbon::parse('2026-05-01T00:00:00Z'),
            Carbon::parse('2026-05-02T00:00:00Z'),
        );

        $summary = AvailabilityDailySummary::query()
            ->forProductStore($product->id, $this->store->id)
            ->where('date', Carbon::parse('2026-05-01')->startOfDay())
            ->first();

        // Daily resolution: the single 24h slot's availability equals the day's
        // min and max — a 1:1 copy.
        expect($summary)->not->toBeNull()
            ->and($summary->min_available)->toBe(4)
            ->and($summary->max_available)->toBe(4)
            ->and($summary->has_shortage)->toBeFalse();
    });

    it('upserts (does not duplicate) the daily summary on repeated recalculation', function () {
        useResolution(AvailabilityResolution::HalfDaily);
        $pipeline = app(RecalculationPipeline::class);

        $product = Product::factory()->bulk()->create();
        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity_held' => 5,
        ]);

        $window = [
            Carbon::parse('2026-05-01T00:00:00Z'),
            Carbon::parse('2026-05-02T00:00:00Z'),
        ];

        $pipeline->recalculate($product->id, $this->store->id, ...$window);
        $pipeline->recalculate($product->id, $this->store->id, ...$window);

        expect(AvailabilityDailySummary::query()
            ->forProductStore($product->id, $this->store->id)
            ->count())->toBe(1);
    });
});
