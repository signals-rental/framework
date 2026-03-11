<?php

namespace App\Actions\Members;

use App\Events\AuditableEvent;
use App\Models\MemberRelationship;
use Illuminate\Support\Facades\Gate;

class DeleteMemberRelationship
{
    public function __invoke(MemberRelationship $relationship): void
    {
        Gate::authorize('members.edit');

        event(new AuditableEvent($relationship, 'member.relationship.deleted'));

        $relationship->delete();
    }
}
