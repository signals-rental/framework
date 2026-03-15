<?php

namespace App\Actions\Members;

use App\Events\AuditableEvent;
use App\Models\Member;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AnonymiseMember
{
    public function __invoke(Member $member): Member
    {
        Gate::authorize('members.delete');

        // Prevent self-anonymisation
        $currentUser = auth()->user();
        if ($currentUser && $currentUser->member_id === $member->id) {
            throw ValidationException::withMessages([
                'member' => 'You cannot anonymise your own member record.',
            ]);
        }

        $oldValues = [
            'name' => $member->name,
            'description' => $member->description,
        ];

        // Anonymise PII on the member record
        $member->update([
            'name' => 'Anonymised Member',
            'description' => null,
            'icon_url' => null,
            'icon_thumb_url' => null,
        ]);

        // Delete related contact details
        $member->emails()->delete();
        $member->phones()->delete();
        $member->addresses()->delete();
        $member->links()->delete();

        event(new AuditableEvent(
            $member,
            'member.anonymised',
            $oldValues,
            ['name' => 'Anonymised Member'],
        ));

        return $member->refresh();
    }
}
