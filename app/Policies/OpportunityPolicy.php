<?php

namespace App\Policies;

use App\Policies\Traits\AuthorizesByPermission;

class OpportunityPolicy
{
    use AuthorizesByPermission;

    protected function viewPermission(): string
    {
        return 'opportunities.view';
    }

    protected function managePermission(): string
    {
        return 'opportunities.create';
    }

    protected function createPermission(): string
    {
        return 'opportunities.create';
    }

    protected function editPermission(): string
    {
        return 'opportunities.edit';
    }

    protected function deletePermission(): string
    {
        return 'opportunities.delete';
    }
}
