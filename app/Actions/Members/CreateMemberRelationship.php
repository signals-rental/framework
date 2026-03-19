<?php

namespace App\Actions\Members;

use App\Data\Members\CreateMemberRelationshipData;
use App\Data\Members\MemberRelationshipData;
use App\Events\AuditableEvent;
use App\Models\Member;
use App\Models\MemberRelationship;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateMemberRelationship
{
    public function __invoke(Member $member, CreateMemberRelationshipData $data): MemberRelationshipData
    {
        Gate::authorize('members.edit');

        $exists = MemberRelationship::query()
            ->where(function ($q) use ($member, $data): void {
                $q->where('member_id', $member->id)
                    ->where('related_member_id', $data->related_member_id);
            })
            ->orWhere(function ($q) use ($member, $data): void {
                $q->where('member_id', $data->related_member_id)
                    ->where('related_member_id', $member->id);
            })
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'related_member_id' => ['A relationship between these members already exists.'],
            ]);
        }

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
