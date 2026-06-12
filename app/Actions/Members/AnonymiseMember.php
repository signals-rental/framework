<?php

namespace App\Actions\Members;

use App\Events\AuditableEvent;
use App\Models\Member;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\DB;
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

        DB::transaction(function () use ($member, $oldValues): void {
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
        });

        // Dispatch the webhook only after the transaction has committed. All queue
        // connections use after_commit: false, so dispatching inside the closure
        // would queue a delivery even if the transaction later rolled back.
        app(WebhookService::class)->dispatch('member.anonymised', [
            'id' => $member->id,
        ]);

        return $member->refresh();
    }
}
