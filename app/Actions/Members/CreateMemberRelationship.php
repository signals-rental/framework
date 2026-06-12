<?php

namespace App\Actions\Members;

use App\Data\Members\CreateMemberRelationshipData;
use App\Data\Members\MemberRelationshipData;
use App\Enums\MembershipType;
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

        $relatedMember = Member::findOrFail($data->related_member_id);

        $this->assertAllowedPair($member->membership_type, $relatedMember->membership_type);

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

    /**
     * Enforce the allowed relationship-pair matrix.
     *
     * Allowed: contact↔organisation, contact↔venue, contact↔contact.
     * Forbidden: organisation↔venue, organisation↔organisation, venue↔venue,
     * and any pair involving a user-type member (staff records).
     *
     * @throws ValidationException
     */
    private function assertAllowedPair(MembershipType $source, MembershipType $target): void
    {
        if ($source === MembershipType::User || $target === MembershipType::User) {
            throw ValidationException::withMessages([
                'related_member_id' => ['User-type members cannot have relationships.'],
            ]);
        }

        $pair = [$source, $target];
        $hasContact = in_array(MembershipType::Contact, $pair, true);

        // Every allowed combination involves at least one contact.
        if (! $hasContact) {
            $message = match (true) {
                $source === MembershipType::Organisation && $target === MembershipType::Venue,
                $source === MembershipType::Venue && $target === MembershipType::Organisation => 'Organisations and venues cannot be linked directly. Link them through a shared contact.',
                $source === MembershipType::Organisation && $target === MembershipType::Organisation => 'Organisations cannot be linked to other organisations. Only contacts associate with organisations.',
                $source === MembershipType::Venue && $target === MembershipType::Venue => 'Venues cannot be linked to other venues.',
                default => 'These member types cannot be linked.',
            };

            throw ValidationException::withMessages([
                'related_member_id' => [$message],
            ]);
        }
    }
}
