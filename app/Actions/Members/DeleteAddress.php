<?php

namespace App\Actions\Members;

use App\Events\AuditableEvent;
use App\Models\Address;
use Illuminate\Support\Facades\Gate;

class DeleteAddress
{
    public function __invoke(Address $address): void
    {
        Gate::authorize('members.edit');

        event(new AuditableEvent($address, 'member.address.deleted'));

        $address->delete();
    }
}
