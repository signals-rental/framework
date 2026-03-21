<?php

namespace App\Policies;

use App\Policies\Traits\AuthorizesByPermission;

class MemberPolicy
{
    use AuthorizesByPermission;

    protected function viewPermission(): string
    {
        return 'members.view';
    }

    protected function managePermission(): string
    {
        return 'members.create';
    }

    protected function createPermission(): string
    {
        return 'members.create';
    }

    protected function editPermission(): string
    {
        return 'members.edit';
    }

    protected function deletePermission(): string
    {
        return 'members.delete';
    }
}
