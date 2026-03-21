<?php

namespace App\Policies;

use App\Policies\Traits\AuthorizesByPermission;

class StockLevelPolicy
{
    use AuthorizesByPermission;

    protected function viewPermission(): string
    {
        return 'stock.view';
    }

    protected function managePermission(): string
    {
        return 'stock.adjust';
    }
}
