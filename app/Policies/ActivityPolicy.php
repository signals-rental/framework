<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\User;
use App\Policies\Traits\AuthorizesByPermission;

class ActivityPolicy
{
    use AuthorizesByPermission;

    protected function viewPermission(): string
    {
        return 'activities.view';
    }

    protected function managePermission(): string
    {
        return 'activities.create';
    }

    protected function createPermission(): string
    {
        return 'activities.create';
    }

    protected function editPermission(): string
    {
        return 'activities.edit';
    }

    protected function deletePermission(): string
    {
        return 'activities.delete';
    }

    public function complete(User $user, Activity $activity): bool
    {
        return $user->can('activities.complete');
    }
}
