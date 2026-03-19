<?php

namespace App\Policies;

use App\Policies\Traits\AuthorizesByPermission;

class EmailTemplatePolicy
{
    use AuthorizesByPermission;

    protected function managePermission(): string
    {
        return 'email-templates.manage';
    }
}
