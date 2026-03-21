<?php

namespace App\Policies;

use App\Policies\Traits\AuthorizesByPermission;

class ProductGroupPolicy
{
    use AuthorizesByPermission;

    protected function viewPermission(): string
    {
        return 'products.view';
    }

    protected function managePermission(): string
    {
        return 'products.edit';
    }

    protected function deletePermission(): string
    {
        return 'products.delete';
    }
}
