<?php

namespace App\Policies;

use App\Models\EmailTemplate;
use App\Models\User;

class EmailTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('email-templates.manage');
    }

    public function view(User $user, EmailTemplate $emailTemplate): bool
    {
        return $user->can('email-templates.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('email-templates.manage');
    }

    public function update(User $user, EmailTemplate $emailTemplate): bool
    {
        return $user->can('email-templates.manage');
    }

    public function delete(User $user, EmailTemplate $emailTemplate): bool
    {
        return $user->can('email-templates.manage');
    }
}
