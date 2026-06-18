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

describe('GET /api/v1/products/{product}/available-assets', function () {
    it('lists serialised assets free across the window, excluding demanded ones', function () {
        $token = $this->owner->createToken('test', ['availability:read'])->plainTextToken;

        $product = Product::factory()->serialised()->create();
        $free = StockLevel::factory()->serialised()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
        ]);
        $busy = StockLevel::factory()->serialised()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
        ]);

        Demand::factory()
            ->serialised()
            ->phase(DemandPhase::Committed)
            ->window(Carbon::parse('2026-07-01T00:00:00Z'), Carbon::parse('2026-07-05T00:00:00Z'))
            ->create([
                'product_id' => $product->id,
                'store_id' => $this->store->id,
                'asset_id' => $busy->id,
                'quantity' => 1,
            ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/products/{$product->id}/available-assets?store_id={$this->store->id}&from=2026-07-02&to=2026-07-04")
            ->assertOk();

        expect($response->json('available_assets'))->toHaveCount(1)
            ->and($response->json('available_assets.0.id'))->toBe($free->id)
            ->and($response->json('meta.total'))->toBe(1);
    });

    it('paginates the assets with real per_page/page/total meta', function () {
        $token = $this->owner->createToken('test', ['availability:read'])->plainTextToken;

        $product = Product::factory()->serialised()->create();
        // Two free serialised assets, no demands → both available across the window.
        StockLevel::factory()->serialised()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
        ]);
        StockLevel::factory()->serialised()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/products/{$product->id}/available-assets?store_id={$this->store->id}&from=2026-07-02&to=2026-07-04&per_page=1")
            ->assertOk();

        // Real ->paginate(): per_page round-trips, total counts ALL matches, and
        // only one row comes back on the page (not get()+faked meta).
        expect($response->json('meta.per_page'))->toBe(1)
            ->and($response->json('meta.page'))->toBe(1)
            ->and($response->json('meta.total'))->toBeGreaterThanOrEqual(2)
            ->and($response->json('available_assets'))->toHaveCount(1);
    });

    it('requires the availability:read ability', function () {
        $token = $this->owner->createToken('test', ['stock:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/products/{$this->product->id}/available-assets?store_id={$this->store->id}&from=2026-07-02&to=2026-07-04")
            ->assertForbidden();
    });

    it('validates that store_id, from and to are present', function () {
        $token = $this->owner->createToken('test', ['availability:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/products/{$this->product->id}/available-assets")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['store_id', 'from', 'to']);
    });
});
