<?php

namespace App\Policies\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait ChecksStoreAccess
{
    /**
     * Check whether the user can access the store that owns the given model.
     *
     * Returns true when:
     *  - the user is unrestricted (owner/admin → accessibleStoreIds() returns null)
     *  - the model has no store_id (global entity)
     *  - the model's store_id is in the user's accessible stores
     */
    protected function canAccessStore(User $user, Model $model): bool
    {
        $storeIds = $user->accessibleStoreIds();

        // Unrestricted user (owner/admin)
        if ($storeIds === null) {
            return true;
        }

        // Model is not store-scoped
        /** @var int|null $storeId */
        $storeId = $model->getAttribute('store_id');

        if ($storeId === null) {
            return true;
        }

        return in_array($storeId, $storeIds, true);
    }
}
