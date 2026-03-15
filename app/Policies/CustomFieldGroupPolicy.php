<?php

namespace App\Policies;

use App\Models\CustomFieldGroup;
use App\Models\User;

class CustomFieldGroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('custom-fields.view');
    }

    public function view(User $user, CustomFieldGroup $customFieldGroup): bool
    {
        return $user->can('custom-fields.view');
    }

    public function create(User $user): bool
    {
        return $user->can('custom-fields.manage');
    }

    public function update(User $user, CustomFieldGroup $customFieldGroup): bool
    {
        return $user->can('custom-fields.manage');
    }

    public function delete(User $user, CustomFieldGroup $customFieldGroup): bool
    {
        return $user->can('custom-fields.manage');
    }
}
