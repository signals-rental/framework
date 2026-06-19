<?php

use App\Enums\OpportunityStatus;
use App\Enums\ReleasePoint;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Product;
use App\Models\Store;
use App\Services\Availability\OpportunityItemDemandResolver;
use App\Settings\AvailabilitySettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

describe('store include_in_default_queries column + scope', function () {
    it('has the column defaulting to true', function () {
        expect(Schema::hasColumn('stores', 'include_in_default_queries'))->toBeTrue();

        $store = Store::factory()->create();

        expect($store->fresh()->include_in_default_queries)->toBeTrue();
    });

    it('scopeInDefaultQueries excludes stores flagged out', function () {
        $included = Store::factory()->create(['include_in_default_queries' => true]);
        $excluded = Store::factory()->create(['include_in_default_queries' => false]);

        $ids = Store::query()->inDefaultQueries()->pluck('id');

        expect($ids)->toContain($included->id)
            ->and($ids)->not->toContain($excluded->id);
    });
});

describe('opportunity_items store overrides', function () {
    it('has nullable dispatch/return store columns', function () {
        expect(Schema::hasColumn('opportunity_items', 'dispatch_store_id'))->toBeTrue()
            ->and(Schema::hasColumn('opportunity_items', 'return_store_id'))->toBeTrue();
    });

    it('the demand resolver claims against dispatch_store_id over the opportunity store', function () {
        $oppStore = Store::factory()->create();
        $dispatchStore = Store::factory()->create();
        $product = Product::factory()->bulk()->create([
            'buffer_before_minutes' => 0,
            'post_rent_unavailability' => 0,
        ]);

        $opportunity = Opportunity::factory()->create([
            'state' => OpportunityStatus::OrderActive->state()->value,
            'status' => OpportunityStatus::OrderActive->statusValue(),
            'store_id' => $oppStore->id,
            'starts_at' => Carbon::parse('2026-08-01T09:00:00Z'),
            'ends_at' => Carbon::parse('2026-08-05T17:00:00Z'),
        ]);
        $item = OpportunityItem::factory()->for($opportunity)->create([
            'item_type' => Product::class,
            'item_id' => $product->id,
            'quantity' => 2,
            'dispatch_store_id' => $dispatchStore->id,
        ]);

        (new OpportunityItemDemandResolver)->syncDemands($item);

        $demand = Demand::query()->where('source_id', $item->id)->firstOrFail();

        expect((int) $demand->store_id)->toBe($dispatchStore->id);
    });

    it('inherits the opportunity store when no dispatch override is set', function () {
        $oppStore = Store::factory()->create();
        $product = Product::factory()->bulk()->create();

        $opportunity = Opportunity::factory()->create([
            'state' => OpportunityStatus::OrderActive->state()->value,
            'status' => OpportunityStatus::OrderActive->statusValue(),
            'store_id' => $oppStore->id,
            'starts_at' => Carbon::parse('2026-08-01T09:00:00Z'),
            'ends_at' => Carbon::parse('2026-08-05T17:00:00Z'),
        ]);
        $item = OpportunityItem::factory()->for($opportunity)->create([
            'item_type' => Product::class,
            'item_id' => $product->id,
            'quantity' => 2,
        ]);

        (new OpportunityItemDemandResolver)->syncDemands($item);

        $demand = Demand::query()->where('source_id', $item->id)->firstOrFail();

        expect((int) $demand->store_id)->toBe($oppStore->id);
    });
});

describe('AvailabilitySettings new keys', function () {
    it('defines defaults, rules and types for every spec key', function () {
        $settings = new AvailabilitySettings;

        $keys = [
            'release_point', 'default_turnaround_hours', 'overdue_check_interval',
            'shortage_acknowledgement_required', 'shortage_notification_roles',
            'overbooking_approval_required', 'shortage_warnings_at_quote',
            'daily_summary_retention_years', 'event_log_retention_months',
            'async_threshold_products', 'kit_nesting_max_depth',
            'recalculation_lock_timeout_ms',
        ];

        foreach ($keys as $key) {
            expect($settings->defaults())->toHaveKey($key)
                ->and($settings->rules())->toHaveKey($key);
        }

        expect($settings->defaults()['release_point'])->toBe(ReleasePoint::Returned->value)
            ->and($settings->defaults()['shortage_notification_roles'])->toBe(['coordinator', 'manager'])
            ->and($settings->types()['shortage_notification_roles'])->toBe('json');
    });
});
