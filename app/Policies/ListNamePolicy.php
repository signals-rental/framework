<?php

namespace App\Policies;

use App\Policies\Traits\AuthorizesByPermission;

class ListNamePolicy
{
    use AuthorizesByPermission;

    protected function viewPermission(): string
    {
        return 'list-values.view';
    }

    protected function managePermission(): string
    {
        return 'list-values.manage';
    }
}
