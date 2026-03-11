<?php

namespace App\Actions\Members;

use App\Events\AuditableEvent;
use App\Models\Phone;
use Illuminate\Support\Facades\Gate;

class DeletePhone
{
    public function __invoke(Phone $phone): void
    {
        Gate::authorize('members.edit');

        event(new AuditableEvent($phone, 'member.phone.deleted'));

        $phone->delete();
    }
}
