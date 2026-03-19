<?php

namespace App\Policies;

use App\Policies\Traits\AuthorizesByPermission;

class WebhookPolicy
{
    use AuthorizesByPermission;

    protected function managePermission(): string
    {
        return 'webhooks.manage';
    }
}
