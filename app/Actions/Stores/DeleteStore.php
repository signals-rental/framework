<?php

namespace App\Actions\Stores;

use App\Events\AuditableEvent;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Delete a store location.
 *
 * Authorises via StorePolicy (settings.manage), guards against deleting the
 * default store, performs the delete inside a transaction, and records an
 * audit-trail entry.
 */
class DeleteStore
{
    public function __invoke(Store $store): void
    {
        Gate::authorize('delete', $store);

        if ($store->is_default) {
            throw ValidationException::withMessages([
                'store' => [__('The default store cannot be deleted.')],
            ]);
        }

        DB::transaction(function () use ($store): void {
            event(new AuditableEvent($store, 'store.deleted'));

            $store->delete();
        });
    }
}
