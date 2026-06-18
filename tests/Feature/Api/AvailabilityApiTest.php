<?php

use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Enums\AvailabilityResolution;
use App\Enums\DemandPhase;
use App\Models\Demand;
use App\Models\Product;
use App\Models\StockLevel;
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
    $this->product = Product::factory()->bulk()->create();
    StockLevel::factory()->bulk()->create([
        'product_id' => $this->product->id,
        'store_id' => $this->store->id,
        'quantity_held' => 10,
    ]);

    Demand::factory()
        ->phase(DemandPhase::Committed)
        ->window(Carbon::parse('2026-07-01T00:00:00Z'), Carbon::parse('2026-07-04T00:00:00Z'))
        ->create([
            'product_id' => $this->product->id,
            'store_id' => $this->store->id,
            'quantity' => 4,
        ]);
});

describe('GET /api/v1/availability (point)', function () {
    it('returns point availability for a date', function () {
        $token = $this->owner->createToken('test', ['availability:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/availability?product_id={$this->product->id}&store_id={$this->store->id}&date=2026-07-02")
            ->assertOk();

        expect($response->json('availability.total_stock'))->toBe(10)
            ->and($response->json('availability.total_demanded'))->toBe(4)
            ->and($response->json('availability.available'))->toBe(6);
    });

    it('requires authentication', function () {
        $this->getJson("/api/v1/availability?product_id={$this->product->id}&store_id={$this->store->id}&date=2026-07-02")
            ->assertUnauthorized();
    });

    it('requires the availability:read ability', function () {
        $token = $this->owner->createToken('test', ['stock:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/availability?product_id={$this->product->id}&store_id={$this->store->id}&date=2026-07-02")
            ->assertForbidden();
    });

    it('validates that product_id and store_id are present', function () {
        $token = $this->owner->createToken('test', ['availability:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/availability?date=2026-07-02')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_id', 'store_id']);
    });
});

describe('GET /api/v1/availability (range)', function () {
    it('returns range availability from snapshots with freshness', function () {
        $token = $this->owner->createToken('test', ['availability:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/availability?product_id={$this->product->id}&store_id={$this->store->id}&from=2026-07-01&to=2026-07-04")
            ->assertOk();

        expect($response->json('availability.slots'))->toHaveCount(3)
            ->and($response->json('availability.min_available'))->toBe(6)
            ->and($response->json('availability.calculated_at'))->not->toBeNull();
    });

    it('rejects mixing date with from/to', function () {
        $token = $this->owner->createToken('test', ['availability:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/availability?product_id={$this->product->id}&store_id={$this->store->id}&date=2026-07-02&from=2026-07-01&to=2026-07-04")
            ->assertUnprocessable();
    });
});

describe('GET /api/v1/products/{product}/availability', function () {
    it('returns availability for the bound product', function () {
        $token = $this->owner->createToken('test', ['availability:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/products/{$this->product->id}/availability?store_id={$this->store->id}&date=2026-07-02")
            ->assertOk();

        expect($response->json('availability.product_id'))->toBe($this->product->id)
            ->and($response->json('availability.available'))->toBe(6);
    });
});
