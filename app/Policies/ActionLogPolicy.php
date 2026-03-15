<?php

namespace App\Policies;

use App\Models\ActionLog;
use App\Models\User;

class ActionLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('action-log.view');
    }

    public function view(User $user, ActionLog $actionLog): bool
    {
        return $user->can('action-log.view');
    }
}
