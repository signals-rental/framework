<?php

namespace App\Policies;

use App\Policies\Traits\AuthorizesByPermission;

class ExchangeRatePolicy
{
    use AuthorizesByPermission;

    protected function managePermission(): string
    {
        return 'settings.manage';
    }
}
