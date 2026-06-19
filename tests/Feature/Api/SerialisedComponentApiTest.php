<?php

use App\Models\Product;
use App\Models\SerialisedComponent;
use App\Models\Store;
use App\Models\User;
use App\Services\Availability\KitAvailabilityCalculator;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->owner()->create();

    $this->kit = Product::factory()->create();
    $this->componentProduct = Product::factory()->create();
});

describe('GET /api/v1/products/{product}/components', function () {
    it('lists a kit\'s components with the read ability', function () {
        SerialisedComponent::factory()->create([
            'product_id' => $this->kit->id,
            'component_product_id' => $this->componentProduct->id,
            'quantity' => 3,
        ]);

        $token = $this->owner->createToken('test', ['kits:read'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/products/{$this->kit->id}/components")
            ->assertOk()
            ->assertJsonStructure(['components', 'meta']);

        expect($response->json('components'))->toHaveCount(1)
            ->and($response->json('components.0.component_product_id'))->toBe($this->componentProduct->id)
            ->and($response->json('components.0.component_name'))->toBe($this->componentProduct->name);
    });

    it('requires the kits:read ability', function () {
        $token = $this->owner->createToken('test', ['products:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/products/{$this->kit->id}/components")
            ->assertForbidden();
    });
});

describe('POST /api/v1/products/{product}/components', function () {
    it('adds a component and flips is_kit true', function () {
        expect($this->kit->fresh()->is_kit)->toBeFalse();

        $token = $this->owner->createToken('test', ['kits:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/products/{$this->kit->id}/components", [
                'component_product_id' => $this->componentProduct->id,
                'quantity' => 2,
                'binding' => 'pool',
            ])
            ->assertCreated()
            ->assertJsonPath('component.component_product_id', $this->componentProduct->id);

        expect($this->kit->fresh()->is_kit)->toBeTrue();
        $this->assertDatabaseHas('serialised_components', [
            'product_id' => $this->kit->id,
            'component_product_id' => $this->componentProduct->id,
        ]);
    });

    it('rejects a component that is the product itself as a 422', function () {
        $token = $this->owner->createToken('test', ['kits:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/products/{$this->kit->id}/components", [
                'component_product_id' => $this->kit->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('component_product_id');
    });

    it('rejects a circular composition as a 422', function () {
        // kit -> componentProduct already exists; now make componentProduct a kit
        // whose own component is `kit` — a cycle.
        SerialisedComponent::factory()->create([
            'product_id' => $this->kit->id,
            'component_product_id' => $this->componentProduct->id,
        ]);

        $token = $this->owner->createToken('test', ['kits:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/products/{$this->componentProduct->id}/components", [
                'component_product_id' => $this->kit->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('component_product_id');
    });

    it('rejects exceeding the maximum nesting depth as a 422', function () {
        config(['availability.kit_nesting_max_depth' => 1]);

        // Depth-1 chain (A -> B) already maxes the bound; nesting B -> C would
        // make a depth-2 path through A and is rejected.
        $a = Product::factory()->create();
        $b = Product::factory()->create();
        $c = Product::factory()->create();
        SerialisedComponent::factory()->create([
            'product_id' => $a->id,
            'component_product_id' => $b->id,
        ]);

        $token = $this->owner->createToken('test', ['kits:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/products/{$b->id}/components", [
                'component_product_id' => $c->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('component_product_id');
    });

    it('rejects a duplicate component as a 422', function () {
        SerialisedComponent::factory()->create([
            'product_id' => $this->kit->id,
            'component_product_id' => $this->componentProduct->id,
        ]);

        $token = $this->owner->createToken('test', ['kits:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/products/{$this->kit->id}/components", [
                'component_product_id' => $this->componentProduct->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('component_product_id');
    });
});

describe('PATCH /api/v1/products/{product}/components/{component}', function () {
    it('updates a component line', function () {
        $component = SerialisedComponent::factory()->create([
            'product_id' => $this->kit->id,
            'component_product_id' => $this->componentProduct->id,
            'quantity' => 1,
        ]);

        $token = $this->owner->createToken('test', ['kits:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/products/{$this->kit->id}/components/{$component->id}", [
                'quantity' => 5,
                'binding' => 'fixed',
            ])
            ->assertOk();

        $component->refresh();
        expect((float) $component->quantity)->toBe(5.0)
            ->and($component->binding->value)->toBe('fixed');
    });

    it('returns 404 when the component belongs to another product', function () {
        $other = Product::factory()->create();
        $component = SerialisedComponent::factory()->create([
            'product_id' => $other->id,
            'component_product_id' => $this->componentProduct->id,
        ]);

        $token = $this->owner->createToken('test', ['kits:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/products/{$this->kit->id}/components/{$component->id}", [
                'quantity' => 5,
            ])
            ->assertNotFound();
    });
});

describe('DELETE /api/v1/products/{product}/components/{component}', function () {
    it('removes the last component and flips is_kit false', function () {
        $component = SerialisedComponent::factory()->create([
            'product_id' => $this->kit->id,
            'component_product_id' => $this->componentProduct->id,
        ]);
        $this->kit->forceFill(['is_kit' => true])->save();

        $token = $this->owner->createToken('test', ['kits:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/products/{$this->kit->id}/components/{$component->id}")
            ->assertNoContent();

        expect($this->kit->fresh()->is_kit)->toBeFalse();
        $this->assertDatabaseMissing('serialised_components', ['id' => $component->id]);
    });

    it('keeps is_kit true while other components remain', function () {
        $keep = SerialisedComponent::factory()->create([
            'product_id' => $this->kit->id,
            'component_product_id' => $this->componentProduct->id,
        ]);
        $remove = SerialisedComponent::factory()->create([
            'product_id' => $this->kit->id,
            'component_product_id' => Product::factory()->create()->id,
        ]);
        $this->kit->forceFill(['is_kit' => true])->save();

        $token = $this->owner->createToken('test', ['kits:write'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/products/{$this->kit->id}/components/{$remove->id}")
            ->assertNoContent();

        expect($this->kit->fresh()->is_kit)->toBeTrue();
        $this->assertDatabaseHas('serialised_components', ['id' => $keep->id]);
    });

    it('requires the kits:write ability', function () {
        $component = SerialisedComponent::factory()->create([
            'product_id' => $this->kit->id,
            'component_product_id' => $this->componentProduct->id,
        ]);

        $token = $this->owner->createToken('test', ['kits:read'])->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/products/{$this->kit->id}/components/{$component->id}")
            ->assertForbidden();
    });
});

it('the kit availability calculator reads the composition after API edits', function () {
    $store = Store::factory()->create(['timezone' => 'UTC']);
    $component = Product::factory()->create();

    $token = $this->owner->createToken('test', ['kits:write'])->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/products/{$this->kit->id}/components", [
            'component_product_id' => $component->id,
            'quantity' => 1,
            'binding' => 'pool',
        ])
        ->assertCreated();

    // The composition is now visible to the read-time calculator (no error, and
    // a pool component is registered against the kit).
    expect($this->kit->fresh()->isKit())->toBeTrue();

    $calculator = app(KitAvailabilityCalculator::class);
    $range = $calculator->calculate(
        $this->kit->id,
        $store->id,
        Carbon::parse('2026-07-01T00:00:00Z'),
        Carbon::parse('2026-07-02T00:00:00Z'),
    );

    expect($range->slots)->toBeArray();
});
