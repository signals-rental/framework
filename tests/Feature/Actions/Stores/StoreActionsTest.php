<?php

use App\Actions\Stores\CreateStore;
use App\Actions\Stores\DeleteStore;
use App\Actions\Stores\UpdateStore;
use App\Events\AuditableEvent;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

describe('CreateStore', function () {
    it('creates a store and dispatches an AuditableEvent', function () {
        $this->actingAs(User::factory()->owner()->create());
        Event::fake([AuditableEvent::class]);

        $store = (new CreateStore)(['name' => 'New Store', 'country_code' => 'GB']);

        expect($store->name)->toBe('New Store');
        expect(Store::where('name', 'New Store')->exists())->toBeTrue();

        Event::assertDispatched(AuditableEvent::class, fn (AuditableEvent $e) => $e->action === 'store.created' && $e->model->is($store));
    });

    it('flags the first store as default', function () {
        $this->actingAs(User::factory()->owner()->create());

        $store = (new CreateStore)(['name' => 'First Store', 'country_code' => 'GB']);

        expect($store->is_default)->toBeTrue();
    });

    it('does not flag subsequent stores as default', function () {
        $this->actingAs(User::factory()->owner()->create());
        Store::factory()->default()->create();

        $store = (new CreateStore)(['name' => 'Second Store', 'country_code' => 'GB']);

        expect($store->is_default)->toBeFalse();
    });

    it('records an action_logs row when a store is created', function () {
        $this->actingAs(User::factory()->owner()->create());

        $store = (new CreateStore)(['name' => 'Audit Store', 'country_code' => 'GB']);

        $this->assertDatabaseHas('action_logs', [
            'action' => 'store.created',
            'auditable_type' => Store::class,
            'auditable_id' => $store->id,
        ]);
    });

    it('denies a user without settings.manage permission', function () {
        $this->actingAs(User::factory()->create());

        (new CreateStore)(['name' => 'Blocked', 'country_code' => 'GB']);
    })->throws(AuthorizationException::class);
});

describe('UpdateStore', function () {
    it('updates a store and dispatches an AuditableEvent', function () {
        $this->actingAs(User::factory()->owner()->create());
        $store = Store::factory()->create(['name' => 'Old']);
        Event::fake([AuditableEvent::class]);

        $result = (new UpdateStore)($store, ['name' => 'Renamed']);

        expect($result->name)->toBe('Renamed');
        expect($store->fresh()->name)->toBe('Renamed');

        Event::assertDispatched(AuditableEvent::class, fn (AuditableEvent $e) => $e->action === 'store.updated');
    });

    it('records an action_logs row when a store is updated', function () {
        $this->actingAs(User::factory()->owner()->create());
        $store = Store::factory()->create();

        (new UpdateStore)($store, ['name' => 'Updated Name']);

        $this->assertDatabaseHas('action_logs', [
            'action' => 'store.updated',
            'auditable_type' => Store::class,
            'auditable_id' => $store->id,
        ]);
    });

    it('denies a user without settings.manage permission', function () {
        $this->actingAs(User::factory()->create());
        $store = Store::factory()->create();

        (new UpdateStore)($store, ['name' => 'Blocked']);
    })->throws(AuthorizationException::class);
});

describe('DeleteStore', function () {
    it('deletes a non-default store and dispatches an AuditableEvent', function () {
        $this->actingAs(User::factory()->owner()->create());
        $store = Store::factory()->create(['name' => 'Doomed']);
        Event::fake([AuditableEvent::class]);

        (new DeleteStore)($store);

        expect(Store::find($store->id))->toBeNull();

        Event::assertDispatched(AuditableEvent::class, fn (AuditableEvent $e) => $e->action === 'store.deleted');
    });

    it('records an action_logs row when a store is deleted', function () {
        $this->actingAs(User::factory()->owner()->create());
        $store = Store::factory()->create();
        $id = $store->id;

        (new DeleteStore)($store);

        $this->assertDatabaseHas('action_logs', [
            'action' => 'store.deleted',
            'auditable_type' => Store::class,
            'auditable_id' => $id,
        ]);
    });

    it('refuses to delete the default store', function () {
        $this->actingAs(User::factory()->owner()->create());
        $store = Store::factory()->default()->create();

        expect(fn () => (new DeleteStore)($store))->toThrow(ValidationException::class);
        expect(Store::find($store->id))->not->toBeNull();
    });

    it('denies a user without settings.manage permission', function () {
        $this->actingAs(User::factory()->create());
        $store = Store::factory()->create();

        (new DeleteStore)($store);
    })->throws(AuthorizationException::class);
});
