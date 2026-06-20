<?php

use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Enums\AvailabilityResolution;
use App\Enums\DemandPhase;
use App\Models\AvailabilityDailySummary;
use App\Models\Demand;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();

    $this->app->bind(AvailabilityResolutionProvider::class, fn () => new class implements AvailabilityResolutionProvider
    {
        public function resolve(): AvailabilityResolution
        {
            return AvailabilityResolution::Daily;
        }
    });

    $this->store = Store::factory()->create(['timezone' => 'UTC']);
    $this->product = Product::factory()->bulk()->create(['name' => 'PA Speaker']);

    $this->token = fn (array $abilities = ['availability:read']) => $this->owner
        ->createToken('test', $abilities)->plainTextToken;
});

describe('GET /api/v1/availability/calendar', function () {
    it('returns a multi-product daily-summary grid for a store', function () {
        $other = Product::factory()->bulk()->create(['name' => 'Lighting Desk']);

        AvailabilityDailySummary::factory()
            ->day(Carbon::parse('2026-07-01'), 7, 10)
            ->create(['product_id' => $this->product->id, 'store_id' => $this->store->id]);
        AvailabilityDailySummary::factory()
            ->day(Carbon::parse('2026-07-02'), 5, 9)
            ->create(['product_id' => $this->product->id, 'store_id' => $this->store->id]);
        AvailabilityDailySummary::factory()
            ->day(Carbon::parse('2026-07-01'), 2, 4)
            ->create(['product_id' => $other->id, 'store_id' => $this->store->id]);

        $token = ($this->token)();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/availability/calendar?store_id={$this->store->id}&from=2026-07-01&to=2026-07-02")
            ->assertOk();

        expect($response->json('calendar.store_id'))->toBe($this->store->id)
            ->and($response->json('calendar.products'))->toHaveCount(2);

        $speaker = collect($response->json('calendar.products'))
            ->firstWhere('product_id', $this->product->id);

        expect($speaker['product_name'])->toBe('PA Speaker')
            ->and($speaker['days'])->toHaveCount(2)
            ->and($speaker['days'][0]['date'])->toBe('2026-07-01')
            ->and($speaker['days'][0]['available'])->toBe(7)
            ->and($speaker['days'][1]['available'])->toBe(5);
    });

    it('surfaces the day pending_checkin count on each calendar cell', function () {
        AvailabilityDailySummary::factory()
            ->day(Carbon::parse('2026-07-01'), 4, 6)
            ->create([
                'product_id' => $this->product->id,
                'store_id' => $this->store->id,
                'pending_checkin_quantity' => 3,
            ]);

        $token = ($this->token)();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/availability/calendar?store_id={$this->store->id}&from=2026-07-01&to=2026-07-01")
            ->assertOk();

        $day = $response->json('calendar.products.0.days.0');

        expect($day['date'])->toBe('2026-07-01')
            ->and($day['available'])->toBe(4)
            ->and($day['has_shortage'])->toBeFalse()
            ->and($day['pending_checkin'])->toBe(3);
    });

    it('narrows the grid to requested product_ids', function () {
        $other = Product::factory()->bulk()->create();

        AvailabilityDailySummary::factory()
            ->day(Carbon::parse('2026-07-01'), 7, 10)
            ->create(['product_id' => $this->product->id, 'store_id' => $this->store->id]);
        AvailabilityDailySummary::factory()
            ->day(Carbon::parse('2026-07-01'), 1, 2)
            ->create(['product_id' => $other->id, 'store_id' => $this->store->id]);

        $token = ($this->token)();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/availability/calendar?store_id={$this->store->id}&from=2026-07-01&to=2026-07-02&product_ids[]={$this->product->id}")
            ->assertOk();

        expect($response->json('calendar.products'))->toHaveCount(1)
            ->and($response->json('calendar.products.0.product_id'))->toBe($this->product->id);
    });

    it('requires the availability:read ability', function () {
        $token = ($this->token)(['stock:read']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/availability/calendar?store_id={$this->store->id}&from=2026-07-01&to=2026-07-02")
            ->assertForbidden();
    });

    it('requires authentication', function () {
        $this->getJson("/api/v1/availability/calendar?store_id={$this->store->id}&from=2026-07-01&to=2026-07-02")
            ->assertUnauthorized();
    });

    it('validates store_id, from and to', function () {
        $token = ($this->token)();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/availability/calendar')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['store_id', 'from', 'to']);
    });
});

describe('GET /api/v1/availability/shortages', function () {
    it('sweeps shortage days from the daily summaries', function () {
        AvailabilityDailySummary::factory()
            ->day(Carbon::parse('2026-07-01'), -2, 0)
            ->create(['product_id' => $this->product->id, 'store_id' => $this->store->id]);
        AvailabilityDailySummary::factory()
            ->day(Carbon::parse('2026-07-02'), 3, 5)
            ->create(['product_id' => $this->product->id, 'store_id' => $this->store->id]);

        $token = ($this->token)();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/availability/shortages?store_id={$this->store->id}&from=2026-07-01&to=2026-07-05")
            ->assertOk();

        // Only the negative day comes back, severity is the magnitude.
        expect($response->json('shortages'))->toHaveCount(1)
            ->and($response->json('shortages.0.product_id'))->toBe($this->product->id)
            ->and($response->json('shortages.0.product_name'))->toBe('PA Speaker')
            ->and($response->json('shortages.0.available'))->toBe(-2)
            ->and($response->json('shortages.0.severity'))->toBe(2)
            ->and($response->json('shortages.0.date'))->toBe('2026-07-01');
    });

    it('requires the availability:read ability', function () {
        $token = ($this->token)(['stock:read']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/availability/shortages?store_id={$this->store->id}&from=2026-07-01&to=2026-07-05")
            ->assertForbidden();
    });

    it('validates from and to', function () {
        $token = ($this->token)();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/availability/shortages')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['from', 'to']);
    });
});

describe('GET /api/v1/availability/{product}/gantt', function () {
    it('returns demand bars decomposed into prep/on-hire/turnaround zones', function () {
        // A buffered demand: 2h prep before, 4h turnaround after the on-hire period.
        Demand::factory()
            ->phase(DemandPhase::Committed)
            ->buffered(
                Carbon::parse('2026-07-10T09:00:00Z'),
                Carbon::parse('2026-07-12T17:00:00Z'),
                Carbon::parse('2026-07-10T07:00:00Z'),
                Carbon::parse('2026-07-12T21:00:00Z'),
            )
            ->create([
                'product_id' => $this->product->id,
                'store_id' => $this->store->id,
                'quantity' => 2,
            ]);

        $token = ($this->token)();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/availability/{$this->product->id}/gantt?store_id={$this->store->id}&from=2026-07-01&to=2026-07-31")
            ->assertOk();

        expect($response->json('gantt.product_id'))->toBe($this->product->id)
            ->and($response->json('gantt.demands'))->toHaveCount(1);

        $bar = $response->json('gantt.demands.0');

        // Zone seams: buffer_before_end == starts_at, buffer_after_start == ends_at,
        // period bounds carry the buffered window.
        expect($bar['quantity'])->toBe(2)
            ->and($bar['source_type'])->toBe('opportunity_item')
            ->and($bar['period_start'])->toStartWith('2026-07-10T07:00:00')
            ->and($bar['buffer_before_end'])->toStartWith('2026-07-10T09:00:00')
            ->and($bar['buffer_after_start'])->toStartWith('2026-07-12T17:00:00')
            ->and($bar['period_end'])->toStartWith('2026-07-12T21:00:00')
            ->and($bar['colour'])->not->toBeNull();
    });

    it('resolves the opportunity subject as the bar source name', function () {
        $opportunity = Opportunity::factory()->create(['subject' => 'Festival Main Stage']);

        Demand::factory()
            ->phase(DemandPhase::Committed)
            ->window(Carbon::parse('2026-07-10T09:00:00Z'), Carbon::parse('2026-07-12T17:00:00Z'))
            ->create([
                'product_id' => $this->product->id,
                'store_id' => $this->store->id,
                'metadata' => ['opportunity_id' => $opportunity->id],
            ]);

        $token = ($this->token)();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/availability/{$this->product->id}/gantt?store_id={$this->store->id}&from=2026-07-01&to=2026-07-31")
            ->assertOk();

        expect($response->json('gantt.demands.0.source_name'))->toBe('Festival Main Stage');
    });

    it('only returns active demands overlapping the window', function () {
        // Voided demand — excluded (inactive).
        Demand::factory()
            ->phase(DemandPhase::Void)
            ->window(Carbon::parse('2026-07-10T09:00:00Z'), Carbon::parse('2026-07-12T17:00:00Z'))
            ->create(['product_id' => $this->product->id, 'store_id' => $this->store->id]);

        // Active demand outside the requested window — excluded (no overlap).
        Demand::factory()
            ->phase(DemandPhase::Committed)
            ->window(Carbon::parse('2026-09-10T09:00:00Z'), Carbon::parse('2026-09-12T17:00:00Z'))
            ->create(['product_id' => $this->product->id, 'store_id' => $this->store->id]);

        $token = ($this->token)();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/availability/{$this->product->id}/gantt?store_id={$this->store->id}&from=2026-07-01&to=2026-07-31")
            ->assertOk();

        expect($response->json('gantt.demands'))->toHaveCount(0);
    });

    it('reports shortage severity as a positive magnitude, not the raw negative', function () {
        // A shortage day (worst availability -2) — gantt severity must be +2.
        AvailabilityDailySummary::factory()
            ->day(Carbon::parse('2026-07-15'), -2, 0)
            ->create(['product_id' => $this->product->id, 'store_id' => $this->store->id]);

        $token = ($this->token)();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/availability/{$this->product->id}/gantt?store_id={$this->store->id}&from=2026-07-01&to=2026-07-31")
            ->assertOk();

        expect($response->json('gantt.shortages'))->toHaveCount(1)
            ->and($response->json('gantt.shortages.0.from'))->toBe('2026-07-15')
            ->and($response->json('gantt.shortages.0.severity'))->toBe(2);
    });

    it('requires the availability:read ability', function () {
        $token = ($this->token)(['stock:read']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/availability/{$this->product->id}/gantt?store_id={$this->store->id}&from=2026-07-01&to=2026-07-31")
            ->assertForbidden();
    });
});
