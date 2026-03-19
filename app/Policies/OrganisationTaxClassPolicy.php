<?php

namespace App\Policies;

use App\Policies\Traits\AuthorizesByPermission;

class OrganisationTaxClassPolicy
{
    use AuthorizesByPermission;

    protected function viewPermission(): string
    {
        return 'tax-classes.view';
    }

    protected function managePermission(): string
    {
        return 'tax-classes.manage';
    }
}
