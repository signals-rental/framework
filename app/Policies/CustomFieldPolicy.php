<?php

namespace App\Policies;

use App\Models\CustomField;
use App\Models\User;

class CustomFieldPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('custom-fields.view');
    }

    public function view(User $user, CustomField $customField): bool
    {
        return $user->can('custom-fields.view');
    }

    public function create(User $user): bool
    {
        return $user->can('custom-fields.manage');
    }

    public function update(User $user, CustomField $customField): bool
    {
        return $user->can('custom-fields.manage');
    }

    public function delete(User $user, CustomField $customField): bool
    {
        return $user->can('custom-fields.manage');
    }
}
