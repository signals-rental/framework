<?php

use App\Enums\AvailabilityEventType;
use App\Models\AvailabilityEvent;
use App\Models\Product;
use App\Models\Store;
use App\Services\Shortages\PipelineShortageEmitter;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->store = Store::factory()->create(['timezone' => 'UTC']);
    $this->product = Product::factory()->rental()->bulk()->create();
    $this->emitter = app(PipelineShortageEmitter::class);
    $this->from = Carbon::parse('2026-07-01T00:00:00Z');
    $this->to = Carbon::parse('2026-07-05T00:00:00Z');
});

it('emits a product/store shortage_detected when a recalc crosses into shortage', function () {
    $this->emitter->emitForRecalc(
        $this->product->id,
        $this->store->id,
        $this->from,
        $this->to,
        crossedIntoShortage: true,
        crossedOutOfShortage: false,
    );

    $event = AvailabilityEvent::query()
        ->where('event_type', AvailabilityEventType::ShortageDetected->value)
        ->where('source_type', 'product_store')
        ->where('product_id', $this->product->id)
        ->where('store_id', $this->store->id)
        ->first();

    expect($event)->not->toBeNull()
        ->and($event->source_id)->toBe($this->product->id)
        ->and($event->payload['reason'])->toBe('recalculation');
});

it('emits a product/store shortage_resolved when a recalc crosses out of shortage', function () {
    $this->emitter->emitForRecalc(
        $this->product->id,
        $this->store->id,
        $this->from,
        $this->to,
        crossedIntoShortage: false,
        crossedOutOfShortage: true,
    );

    expect(AvailabilityEvent::query()
        ->where('event_type', AvailabilityEventType::ShortageResolved->value)
        ->where('source_type', 'product_store')
        ->where('product_id', $this->product->id)
        ->exists())->toBeTrue();
});

it('emits nothing when no crossing occurred', function () {
    $this->emitter->emitForRecalc(
        $this->product->id,
        $this->store->id,
        $this->from,
        $this->to,
        crossedIntoShortage: false,
        crossedOutOfShortage: false,
    );

    expect(AvailabilityEvent::query()
        ->where('source_type', 'product_store')
        ->where('product_id', $this->product->id)
        ->whereIn('event_type', [
            AvailabilityEventType::ShortageDetected->value,
            AvailabilityEventType::ShortageResolved->value,
        ])
        ->exists())->toBeFalse();
});

it('uses a product/store source scope distinct from the opportunity-item listener path', function () {
    // The pipeline emitter writes source_type = 'product_store'; the
    // DetectOrderShortages listener writes source_type = 'opportunity_item'.
    // Asserting the scope here documents the no-double-fire coordination.
    $this->emitter->emitForRecalc(
        $this->product->id,
        $this->store->id,
        $this->from,
        $this->to,
        crossedIntoShortage: true,
        crossedOutOfShortage: false,
    );

    expect(AvailabilityEvent::query()
        ->where('source_type', 'opportunity_item')
        ->where('product_id', $this->product->id)
        ->exists())->toBeFalse();
});
