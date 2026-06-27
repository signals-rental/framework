<?php

use App\Actions\Products\CreateSerialisedComponent;
use App\Actions\Products\UpdateSerialisedComponent;
use App\Data\Products\CreateSerialisedComponentData;
use App\Data\Products\UpdateSerialisedComponentData;
use App\Enums\KitComponentBinding;
use App\Models\Product;
use App\Models\SerialisedComponent;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

// ─── CreateSerialisedComponent: raced unique violation → 422 ──────────

it('translates a raced kit-component unique violation into a 422', function () {
    $kit = Product::factory()->serialised()->create();
    $component = Product::factory()->serialised()->create();

    // Simulate a concurrent create: the instant the action's duplicate-existence
    // SELECT runs, insert the same (product, component) pair directly so the action's
    // own INSERT then violates uq_kit_component and is caught as a ValidationException.
    $injected = false;
    DB::listen(function ($query) use (&$injected, $kit, $component): void {
        if ($injected) {
            return;
        }

        if (str_contains($query->sql, 'serialised_components')
            && str_contains($query->sql, 'exists')) {
            $injected = true;

            DB::table('serialised_components')->insert([
                'product_id' => $kit->id,
                'component_product_id' => $component->id,
                'quantity' => 1,
                'binding' => KitComponentBinding::Pool->value,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    });

    // The QueryException from the raced unique violation is translated into a
    // field-scoped ValidationException by the action's catch block. Assert the
    // field + message so we know it came from the catch (not a raw 500).
    try {
        (new CreateSerialisedComponent)(CreateSerialisedComponentData::from([
            'product_id' => $kit->id,
            'component_product_id' => $component->id,
        ]));
        $this->fail('Expected a ValidationException from the raced unique violation.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('component_product_id')
            ->and($e->errors()['component_product_id'][0])->toBe('This product is already a component of the kit.');
    }

    // The action's whole transaction (including the injected row) rolled back, so no
    // membership survives — proving the failure unwound atomically rather than
    // leaving a half-written kit.
    expect($injected)->toBeTrue()
        ->and(SerialisedComponent::query()->where('product_id', $kit->id)->count())->toBe(0);
});

// ─── UpdateSerialisedComponent: sort_order change is applied ──────────

it('updates a kit component sort order', function () {
    $kit = Product::factory()->serialised()->create();
    $component = SerialisedComponent::factory()->create([
        'product_id' => $kit->id,
        'sort_order' => 0,
    ]);

    $result = (new UpdateSerialisedComponent)($component, UpdateSerialisedComponentData::from([
        'sort_order' => 7,
    ]));

    expect($result->sort_order)->toBe(7)
        ->and($component->fresh()->sort_order)->toBe(7);
});
