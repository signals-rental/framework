<?php

namespace App\Policies;

use App\Policies\Traits\AuthorizesByPermission;

class CustomFieldPolicy
{
    use AuthorizesByPermission;

    protected function viewPermission(): string
    {
        return 'custom-fields.view';
    }

    protected function managePermission(): string
    {
        return 'custom-fields.manage';
    }
}
