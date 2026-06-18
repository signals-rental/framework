<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
|--------------------------------------------------------------------------
| Availability (per-store)
|--------------------------------------------------------------------------
|
| AvailabilityChanged broadcasts on a private per-store channel so a
| calendar/grid UI scoped to a store receives live recalculation nudges.
| Any authenticated user may subscribe in the single-tenant OSF; the
| commercial store-scoping layer narrows this to the user's stores by
| replacing the predicate below.
*/

Broadcast::channel('availability.store.{storeId}', function (User $user, int $storeId): bool {
    return $user->exists && $storeId > 0;
});
