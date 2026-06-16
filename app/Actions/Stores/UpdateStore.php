<?php

namespace App\Actions\Stores;

use App\Data\Stores\UpdateStoreData;
use App\Events\AuditableEvent;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Update a store location.
 *
 * Authorises via StorePolicy (settings.manage), performs the write inside a
 * transaction, and records an audit-trail entry. Following the Spatie Data
 * partial-update convention, only non-null DTO fields are applied — a `null`
 * value means "leave unchanged" rather than "clear the column".
 */
class UpdateStore
{
    public function __invoke(Store $store, UpdateStoreData $data): Store
    {
        Gate::authorize('update', $store);

        return DB::transaction(function () use ($store, $data): Store {
            $attributes = collect($data->toArray())
                ->reject(fn ($value): bool => $value === null)
                ->all();

            $store->update($attributes);

            event(new AuditableEvent($store, 'store.updated'));

            return $store->refresh();
        });
    }
}
