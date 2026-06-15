<?php

namespace App\Actions\Stores;

use App\Events\AuditableEvent;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Create a store location.
 *
 * Authorises via StorePolicy (settings.manage), performs the write inside a
 * transaction, and records an audit-trail entry. The first store created is
 * automatically flagged as the default so store-scoping always resolves.
 */
class CreateStore
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(array $attributes): Store
    {
        Gate::authorize('create', Store::class);

        return DB::transaction(function () use ($attributes): Store {
            if (! array_key_exists('is_default', $attributes)) {
                $attributes['is_default'] = Store::query()->count() === 0;
            }

            $store = Store::create($attributes);

            event(new AuditableEvent($store, 'store.created'));

            return $store;
        });
    }
}
