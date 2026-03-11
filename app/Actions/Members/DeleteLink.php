<?php

namespace App\Actions\Members;

use App\Events\AuditableEvent;
use App\Models\Link;
use Illuminate\Support\Facades\Gate;

class DeleteLink
{
    public function __invoke(Link $link): void
    {
        Gate::authorize('members.edit');

        event(new AuditableEvent($link, 'member.link.deleted'));

        $link->delete();
    }
}
