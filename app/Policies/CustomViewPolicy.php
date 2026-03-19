<?php

namespace App\Policies;

use App\Models\CustomView;
use App\Models\User;

class CustomViewPolicy
{
    /**
     * Anyone can view custom views (visibility filtering happens in scopes).
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Anyone can view a custom view.
     */
    public function view(User $user, CustomView $view): bool
    {
        return true;
    }

    /**
     * Any authenticated user can create views.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Personal views: only the owner can update.
     * Shared/system views: requires settings.manage permission.
     */
    public function update(User $user, CustomView $view): bool
    {
        if ($view->visibility === 'personal') {
            return $view->user_id === $user->id;
        }

        return $user->can('settings.manage');
    }

    /**
     * Personal views: only the owner can delete.
     * System views: cannot be deleted.
     * Shared views: requires settings.manage permission.
     */
    public function delete(User $user, CustomView $view): bool
    {
        if ($view->visibility === 'system') {
            return false;
        }

        if ($view->visibility === 'personal') {
            return $view->user_id === $user->id;
        }

        return $user->can('settings.manage');
    }
}
