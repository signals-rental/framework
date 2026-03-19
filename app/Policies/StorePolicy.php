<?php

namespace App\Policies;

use App\Policies\Traits\AuthorizesByPermission;

class StorePolicy
{
    use AuthorizesByPermission;

    protected function viewPermission(): string
    {
        return 'settings.view';
    }

    protected function managePermission(): string
    {
        return 'settings.manage';
    }
}
