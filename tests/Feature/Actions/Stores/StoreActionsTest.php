<?php

use App\Actions\Stores\CreateStore;
use App\Actions\Stores\DeleteStore;
use App\Actions\Stores\UpdateStore;
use App\Data\Stores\CreateStoreData;
use App\Data\Stores\UpdateStoreData;
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

        $store = (new CreateStore)(CreateStoreData::from(['name' => 'New Store', 'country_code' => 'GB']));

        expect($store->name)->toBe('New Store');
        expect(Store::where('name', 'New Store')->exists())->toBeTrue();

        Event::assertDispatched(AuditableEvent::class, fn (AuditableEvent $e) => $e->action === 'store.created' && $e->model->is($store));
    });

    it('flags the first store as default', function () {
        $this->actingAs(User::factory()->owner()->create());

        $store = (new CreateStore)(CreateStoreData::from(['name' => 'First Store', 'country_code' => 'GB']));

        expect($store->is_default)->toBeTrue();
    });

    it('does not flag subsequent stores as default', function () {
        $this->actingAs(User::factory()->owner()->create());
        Store::factory()->default()->create();

        $store = (new CreateStore)(CreateStoreData::from(['name' => 'Second Store', 'country_code' => 'GB']));

        expect($store->is_default)->toBeFalse();
    });

    it('records an action_logs row when a store is created', function () {
        $this->actingAs(User::factory()->owner()->create());

        $store = (new CreateStore)(CreateStoreData::from(['name' => 'Audit Store', 'country_code' => 'GB']));

        $this->assertDatabaseHas('action_logs', [
            'action' => 'store.created',
            'auditable_type' => Store::class,
            'auditable_id' => $store->id,
        ]);
    });

    it('honours an explicit is_default=false even for the first store', function () {
        $this->actingAs(User::factory()->owner()->create());

        $store = (new CreateStore)(CreateStoreData::from([
            'name' => 'Explicit Non-Default',
            'country_code' => 'GB',
            'is_default' => false,
        ]));

        expect($store->is_default)->toBeFalse();
    });

    it('persists the full set of DTO attributes', function () {
        $this->actingAs(User::factory()->owner()->create());

        $store = (new CreateStore)(CreateStoreData::from([
            'name' => 'Full Store',
            'street' => '1 High St',
            'city' => 'Bristol',
            'county' => 'Avon',
            'postcode' => 'BS1 1AA',
            'country_code' => 'GB',
            'phone' => '0117 000 0000',
            'email' => 'store@example.com',
        ]));

        expect($store->street)->toBe('1 High St')
            ->and($store->city)->toBe('Bristol')
            ->and($store->phone)->toBe('0117 000 0000')
            ->and($store->email)->toBe('store@example.com');
    });

    it('rejects an invalid email via the DTO rules', function () {
        $this->actingAs(User::factory()->owner()->create());

        CreateStoreData::validate(['name' => 'Bad Email', 'email' => 'not-an-email']);
    })->throws(ValidationException::class);

    it('denies a user without settings.manage permission', function () {
        $this->actingAs(User::factory()->create());

        (new CreateStore)(CreateStoreData::from(['name' => 'Blocked', 'country_code' => 'GB']));
    })->throws(AuthorizationException::class);
});

describe('UpdateStore', function () {
    it('updates a store and dispatches an AuditableEvent', function () {
        $this->actingAs(User::factory()->owner()->create());
        $store = Store::factory()->create(['name' => 'Old']);
        Event::fake([AuditableEvent::class]);

        $result = (new UpdateStore)($store, UpdateStoreData::from(['name' => 'Renamed']));

        expect($result->name)->toBe('Renamed');
        expect($store->fresh()->name)->toBe('Renamed');

        Event::assertDispatched(AuditableEvent::class, fn (AuditableEvent $e) => $e->action === 'store.updated');
    });

    it('records an action_logs row when a store is updated', function () {
        $this->actingAs(User::factory()->owner()->create());
        $store = Store::factory()->create();

        (new UpdateStore)($store, UpdateStoreData::from(['name' => 'Updated Name']));

        $this->assertDatabaseHas('action_logs', [
            'action' => 'store.updated',
            'auditable_type' => Store::class,
            'auditable_id' => $store->id,
        ]);
    });

    it('leaves unprovided columns untouched on a partial update', function () {
        $this->actingAs(User::factory()->owner()->create());
        $store = Store::factory()->create(['name' => 'Keep', 'city' => 'Bath']);

        // Only the name is provided; city must not be nulled out.
        (new UpdateStore)($store, UpdateStoreData::from(['name' => 'Renamed']));

        expect($store->fresh()->city)->toBe('Bath');
    });

    it('promotes a store to default via the DTO', function () {
        $this->actingAs(User::factory()->owner()->create());
        $store = Store::factory()->create(['is_default' => false]);

        (new UpdateStore)($store, UpdateStoreData::from(['is_default' => true]));

        expect($store->fresh()->is_default)->toBeTrue();
    });

    it('denies a user without settings.manage permission', function () {
        $this->actingAs(User::factory()->create());
        $store = Store::factory()->create();

        (new UpdateStore)($store, UpdateStoreData::from(['name' => 'Blocked']));
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
