<?php

namespace App\Actions\Stores;

use App\Events\AuditableEvent;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Update a store location.
 *
 * Authorises via StorePolicy (settings.manage), performs the write inside a
 * transaction, and records an audit-trail entry.
 */
class UpdateStore
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(Store $store, array $attributes): Store
    {
        Gate::authorize('update', $store);

        return DB::transaction(function () use ($store, $attributes): Store {
            $store->update($attributes);

            event(new AuditableEvent($store, 'store.updated'));

            return $store->refresh();
        });
    }
}
