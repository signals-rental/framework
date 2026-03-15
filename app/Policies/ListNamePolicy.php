<?php

namespace App\Policies;

use App\Models\ListName;
use App\Models\User;

class ListNamePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('list-values.view');
    }

    public function view(User $user, ListName $listName): bool
    {
        return $user->can('list-values.view');
    }

    public function create(User $user): bool
    {
        return $user->can('list-values.manage');
    }

    public function update(User $user, ListName $listName): bool
    {
        return $user->can('list-values.manage');
    }

    public function delete(User $user, ListName $listName): bool
    {
        return $user->can('list-values.manage');
    }
}
