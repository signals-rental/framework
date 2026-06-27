<?php

use App\Enums\AvailabilityEventType;
use App\Models\ActionLog;
use App\Models\AvailabilityDailySummary;
use App\Models\AvailabilityEvent;
use App\Models\AvailabilitySnapshot;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Support\Carbon;

describe('ActionLog::scopeForOpportunity', function () {
    it('filters logs to a single opportunity', function () {
        $opportunity = Opportunity::factory()->create();
        ActionLog::factory()->create([
            'auditable_type' => Opportunity::class,
            'auditable_id' => $opportunity->id,
        ]);
        ActionLog::factory()->create([
            'auditable_type' => Opportunity::class,
            'auditable_id' => $opportunity->id + 999,
        ]);

        $results = ActionLog::forOpportunity($opportunity->id)->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->auditable_id)->toBe($opportunity->id);
        expect($results->first()->auditable_type)->toBe(Opportunity::class);
    });
});

describe('AvailabilityDailySummary relations', function () {
    it('resolves product and store belongsTo relations', function () {
        $product = Product::factory()->create();
        $store = Store::factory()->create();
        $summary = AvailabilityDailySummary::factory()->create([
            'product_id' => $product->id,
            'store_id' => $store->id,
        ]);

        expect($summary->product->id)->toBe($product->id);
        expect($summary->store->id)->toBe($store->id);
    });
});

describe('AvailabilityEvent relations and scopes', function () {
    it('resolves product, store and demand belongsTo relations', function () {
        $product = Product::factory()->create();
        $store = Store::factory()->create();
        $demand = Demand::factory()->create();

        $event = AvailabilityEvent::factory()->create([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'demand_id' => $demand->id,
        ]);

        expect($event->product->id)->toBe($product->id);
        expect($event->store->id)->toBe($store->id);
        expect($event->demand->id)->toBe($demand->id);
    });

    it('scopes events to a product/store pair', function () {
        $product = Product::factory()->create();
        $store = Store::factory()->create();
        AvailabilityEvent::factory()->create([
            'product_id' => $product->id,
            'store_id' => $store->id,
        ]);
        AvailabilityEvent::factory()->create();

        $results = AvailabilityEvent::forProductStore($product->id, $store->id)->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->product_id)->toBe($product->id);
        expect($results->first()->store_id)->toBe($store->id);
    });

    it('scopes events of a given type', function () {
        AvailabilityEvent::factory()->ofType(AvailabilityEventType::DemandCreated)->create();
        AvailabilityEvent::factory()->ofType(AvailabilityEventType::AvailabilityRecalculated)->create();

        $results = AvailabilityEvent::ofType(AvailabilityEventType::DemandCreated)->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->event_type)->toBe(AvailabilityEventType::DemandCreated);
    });
});

describe('AvailabilitySnapshot relations and scopes', function () {
    it('resolves product and store belongsTo relations', function () {
        $product = Product::factory()->create();
        $store = Store::factory()->create();
        $snapshot = AvailabilitySnapshot::factory()->create([
            'product_id' => $product->id,
            'store_id' => $store->id,
        ]);

        expect($snapshot->product->id)->toBe($product->id);
        expect($snapshot->store->id)->toBe($store->id);
    });

    it('scopes to slots within a half-open window', function () {
        $product = Product::factory()->create();
        $store = Store::factory()->create();
        $base = [
            'product_id' => $product->id,
            'store_id' => $store->id,
        ];

        AvailabilitySnapshot::factory()->create($base + ['slot_start' => Carbon::parse('2026-01-10 00:00:00')]);
        AvailabilitySnapshot::factory()->create($base + ['slot_start' => Carbon::parse('2026-01-20 00:00:00')]);

        $results = AvailabilitySnapshot::forProductStore($product->id, $store->id)
            ->inWindow(Carbon::parse('2026-01-09 00:00:00'), Carbon::parse('2026-01-15 00:00:00'))
            ->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->slot_start->toDateString())->toBe('2026-01-10');
    });

    it('scopes to shortage snapshots (negative availability)', function () {
        AvailabilitySnapshot::factory()->create(['available' => -3]);
        AvailabilitySnapshot::factory()->create(['available' => 5]);

        $results = AvailabilitySnapshot::shortage()->get();

        expect($results)->toHaveCount(1);
        expect($results->first()->available)->toBe(-3);
    });
});
