<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
|--------------------------------------------------------------------------
| Availability
|--------------------------------------------------------------------------
|
| AvailabilityChanged broadcasts on three private channels (availability-engine.md
| §"Real-Time Updates"): the specific product/store channel a Gantt/calendar
| binds to, the store-wide channel, and the global shortages channel. Any
| authenticated user may subscribe in the single-tenant OSF; the commercial
| store-scoping layer narrows the store-bound channels to the user's stores by
| replacing the predicates below.
*/

Broadcast::channel('availability.product.{productId}.store.{storeId}', function (User $user, int $productId, int $storeId): bool {
    return $user->exists && $productId > 0 && $storeId > 0;
});

Broadcast::channel('availability.store.{storeId}', function (User $user, int $storeId): bool {
    return $user->exists && $storeId > 0;
});

Broadcast::channel('availability.shortages', function (User $user): bool {
    return $user->exists;
});
