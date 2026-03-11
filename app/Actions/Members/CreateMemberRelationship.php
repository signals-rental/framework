<?php

namespace App\Actions\Members;

use App\Data\Members\CreateMemberRelationshipData;
use App\Data\Members\MemberRelationshipData;
use App\Events\AuditableEvent;
use App\Models\Member;
use App\Models\MemberRelationship;
use Illuminate\Support\Facades\Gate;

class CreateMemberRelationship
{
    public function __invoke(Member $member, CreateMemberRelationshipData $data): MemberRelationshipData
    {
        Gate::authorize('members.edit');

        $relationship = MemberRelationship::create([
            'member_id' => $member->id,
            'related_member_id' => $data->related_member_id,
            'relationship_type' => $data->relationship_type,
            'is_primary' => $data->is_primary,
        ]);

        event(new AuditableEvent($member, 'member.relationship.created'));

        return MemberRelationshipData::fromModel($relationship);
    }
}
