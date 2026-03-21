<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\User;

class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('activities.view');
    }

    public function view(User $user, Activity $activity): bool
    {
        return $user->can('activities.view');
    }

    public function create(User $user): bool
    {
        return $user->can('activities.create');
    }

    public function update(User $user, Activity $activity): bool
    {
        return $user->can('activities.edit');
    }

    public function delete(User $user, Activity $activity): bool
    {
        return $user->can('activities.delete');
    }

    public function complete(User $user, Activity $activity): bool
    {
        return $user->can('activities.complete');
    }
}
