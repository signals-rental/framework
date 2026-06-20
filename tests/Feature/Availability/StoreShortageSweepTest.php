<?php

use App\Models\AvailabilityDailySummary;
use App\Models\Product;
use App\Models\Store;
use App\Services\AvailabilityService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
    $this->product = Product::factory()->bulk()->create(['name' => 'PA Speaker']);
});

describe('AvailabilityService::getShortages', function () {
    it('returns only shortage days within the window, with severity', function () {
        AvailabilityDailySummary::factory()
            ->day(Carbon::parse('2026-07-01'), -3, 0)
            ->create(['product_id' => $this->product->id, 'store_id' => $this->store->id]);
        AvailabilityDailySummary::factory()
            ->day(Carbon::parse('2026-07-02'), 4, 6)
            ->create(['product_id' => $this->product->id, 'store_id' => $this->store->id]);
        // Out of window — excluded.
        AvailabilityDailySummary::factory()
            ->day(Carbon::parse('2026-08-01'), -5, -1)
            ->create(['product_id' => $this->product->id, 'store_id' => $this->store->id]);

        $shortages = app(AvailabilityService::class)->getShortages(
            $this->store->id,
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-07-31'),
        );

        expect($shortages)->toHaveCount(1)
            ->and($shortages->first()->product_id)->toBe($this->product->id)
            ->and($shortages->first()->product_name)->toBe('PA Speaker')
            ->and($shortages->first()->available)->toBe(-3)
            ->and($shortages->first()->severity)->toBe(3);
    });

    it('scopes to the requested store', function () {
        $otherStore = Store::factory()->create(['timezone' => 'UTC']);

        AvailabilityDailySummary::factory()
            ->day(Carbon::parse('2026-07-01'), -2, 0)
            ->create(['product_id' => $this->product->id, 'store_id' => $this->store->id]);
        AvailabilityDailySummary::factory()
            ->day(Carbon::parse('2026-07-01'), -9, 0)
            ->create(['product_id' => $this->product->id, 'store_id' => $otherStore->id]);

        $shortages = app(AvailabilityService::class)->getShortages(
            $this->store->id,
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-07-31'),
        );

        expect($shortages)->toHaveCount(1)
            ->and($shortages->first()->store_id)->toBe($this->store->id);
    });

    it('sweeps every default-query store when no store is given', function () {
        $otherStore = Store::factory()->create(['timezone' => 'UTC']);

        AvailabilityDailySummary::factory()
            ->day(Carbon::parse('2026-07-01'), -2, 0)
            ->create(['product_id' => $this->product->id, 'store_id' => $this->store->id]);
        AvailabilityDailySummary::factory()
            ->day(Carbon::parse('2026-07-02'), -1, 0)
            ->create(['product_id' => $this->product->id, 'store_id' => $otherStore->id]);

        $shortages = app(AvailabilityService::class)->getShortages(
            0,
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-07-31'),
        );

        expect($shortages)->toHaveCount(2);
    });
});
