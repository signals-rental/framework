<?php

use App\Contracts\Availability\AvailabilityResolutionProvider;
use App\Enums\AvailabilityResolution;
use App\Models\Container;
use App\Models\ContainerItem;
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
    $this->container = Container::factory()->kit()->create(['store_id' => $this->store->id]);
});

/**
 * Build a serialised, free stock level at the test store.
 */
function packableStock(Store $store, ?Product $product = null): StockLevel
{
    $product ??= Product::factory()->serialised()->create();

    return StockLevel::factory()->serialised()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
    ]);
}

describe('GET /api/v1/containers', function () {
    it('lists containers with the read ability', function () {
        $token = $this->owner->createToken('test', ['containers:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/containers')
            ->assertOk()
            ->assertJsonStructure(['containers', 'meta' => ['total', 'per_page', 'page']]);

        expect($response->json('containers'))->toHaveCount(1)
            ->and($response->json('containers.0.id'))->toBe($this->container->id)
            ->and($response->json('containers.0.availability_mode'))->toBe('kit');
    });

    it('filters containers by status', function () {
        Container::factory()->sealed()->create(['store_id' => $this->store->id]);
        $token = $this->owner->createToken('test', ['containers:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/containers?q[status_eq]=open')
            ->assertOk();

        expect($response->json('containers'))->toHaveCount(1)
            ->and($response->json('containers.0.status'))->toBe('open');
    });

    it('requires authentication', function () {
        $this->getJson('/api/v1/containers')->assertUnauthorized();
    });

    it('requires the containers:read ability', function () {
        $token = $this->owner->createToken('test', ['stock:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/containers')
            ->assertForbidden();
    });
});

describe('GET /api/v1/containers/{container}', function () {
    it('shows a container with its packed items', function () {
        $stock = packableStock($this->store);
        ContainerItem::factory()->create([
            'container_id' => $this->container->id,
            'serialised_item_id' => $stock->id,
            'product_id' => $stock->product_id,
        ]);

        $token = $this->owner->createToken('test', ['containers:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/containers/{$this->container->id}")
            ->assertOk()
            ->assertJsonPath('container.id', $this->container->id);

        expect($response->json('container.items'))->toHaveCount(1)
            ->and($response->json('container.items.0.serialised_item_id'))->toBe($stock->id);
    });
});

describe('POST /api/v1/containers/{container}/pack', function () {
    it('packs a serialised item with the write ability', function () {
        $stock = packableStock($this->store);
        $token = $this->owner->createToken('test', ['containers:write'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/containers/{$this->container->id}/pack", [
                'serialised_item_id' => $stock->id,
                'position' => 'Slot 1',
            ])
            ->assertCreated()
            ->assertJsonPath('container_item.serialised_item_id', $stock->id);

        expect($response->json('container_item.position'))->toBe('Slot 1');

        $this->assertDatabaseHas('container_items', [
            'container_id' => $this->container->id,
            'serialised_item_id' => $stock->id,
            'unpacked_at' => null,
        ]);
    });

    it('rejects packing an asset already committed to an opportunity as a 422', function () {
        $stock = packableStock($this->store);

        // An active asset-specific demand over the indefinite container window.
        Demand::factory()->create([
            'product_id' => $stock->product_id,
            'store_id' => $this->store->id,
            'asset_id' => $stock->id,
            'quantity' => 1,
            'source_type' => 'opportunity_item',
            'source_id' => 999001,
            'starts_at' => Carbon::now('UTC')->subDay(),
            'ends_at' => Carbon::now('UTC')->addYears(5),
            'metadata' => [],
        ]);

        $token = $this->owner->createToken('test', ['containers:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/containers/{$this->container->id}/pack", [
                'serialised_item_id' => $stock->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('serialised_item_id');
    });

    it('requires the containers:write ability', function () {
        $stock = packableStock($this->store);
        $token = $this->owner->createToken('test', ['containers:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/containers/{$this->container->id}/pack", [
                'serialised_item_id' => $stock->id,
            ])
            ->assertForbidden();
    });
});

describe('POST /api/v1/containers/{container}/unpack', function () {
    it('unpacks a packed item', function () {
        $stock = packableStock($this->store);
        $token = $this->owner->createToken('test', ['containers:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/containers/{$this->container->id}/pack", [
                'serialised_item_id' => $stock->id,
            ])
            ->assertCreated();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/containers/{$this->container->id}/unpack", [
                'serialised_item_id' => $stock->id,
            ])
            ->assertOk()
            ->assertJsonPath('container_item.serialised_item_id', $stock->id);

        $this->assertDatabaseMissing('container_items', [
            'container_id' => $this->container->id,
            'serialised_item_id' => $stock->id,
            'unpacked_at' => null,
        ]);
    });

    it('rejects unpacking an item that is not packed as a 422', function () {
        $stock = packableStock($this->store);
        $token = $this->owner->createToken('test', ['containers:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/containers/{$this->container->id}/unpack", [
                'serialised_item_id' => $stock->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('serialised_item_id');
    });
});
