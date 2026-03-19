<?php

namespace App\Actions\Members;

use App\Data\Members\MergeMemberData;
use App\Events\AuditableEvent;
use App\Models\Member;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class MergeMember
{
    public function __invoke(MergeMemberData $data): Member
    {
        Gate::authorize('members.delete');

        $primary = Member::findOrFail($data->primary_id);
        $secondary = Member::findOrFail($data->secondary_id);

        if ($primary->membership_type !== $secondary->membership_type) {
            throw new \InvalidArgumentException('Cannot merge members of different types.');
        }

        return DB::transaction(function () use ($primary, $secondary): Member {
            // Migrate polymorphic relations from secondary to primary
            $secondary->addresses()->update([
                'addressable_id' => $primary->id,
            ]);
            $secondary->emails()->update([
                'emailable_id' => $primary->id,
            ]);
            $secondary->phones()->update([
                'phoneable_id' => $primary->id,
            ]);
            $secondary->links()->update([
                'linkable_id' => $primary->id,
            ]);
            $secondary->attachments()->update([
                'attachable_id' => $primary->id,
            ]);

            // Migrate member relationships (skip duplicates)
            $existingRelationIds = $primary->memberRelationships()
                ->pluck('related_member_id')
                ->toArray();

            $secondary->memberRelationships()
                ->whereNotIn('related_member_id', [...$existingRelationIds, $primary->id])
                ->update(['member_id' => $primary->id]);

            $secondary->memberRelationships()->delete();

            // Migrate inverse relationships
            $existingInverseIds = DB::table('member_relationships')
                ->where('related_member_id', $primary->id)
                ->pluck('member_id')
                ->toArray();

            DB::table('member_relationships')
                ->where('related_member_id', $secondary->id)
                ->whereNotIn('member_id', [...$existingInverseIds, $primary->id])
                ->update(['related_member_id' => $primary->id]);

            DB::table('member_relationships')->where('related_member_id', $secondary->id)->delete();

            // Copy missing custom field values
            $existingFieldIds = $primary->customFieldValues()->pluck('custom_field_id')->toArray();
            $secondary->customFieldValues()
                ->whereNotIn('custom_field_id', $existingFieldIds)
                ->update(['entity_id' => $primary->id]);

            $secondary->customFieldValues()->delete();

            // Migrate memberships (skip duplicate store assignments)
            $existingStoreIds = $primary->memberships()->pluck('store_id')->toArray();
            $secondary->memberships()
                ->whereNotIn('store_id', $existingStoreIds)
                ->update(['member_id' => $primary->id]);

            $secondary->memberships()->delete();

            // Audit and archive secondary
            event(new AuditableEvent($primary, 'member.merged', null, null, [
                'secondary_id' => $secondary->id,
                'secondary_name' => $secondary->name,
            ]));

            (new ArchiveMember)($secondary);

            return $primary->fresh();
        });
    }
}
