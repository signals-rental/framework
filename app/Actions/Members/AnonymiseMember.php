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

        // DeliverWebhook sets afterCommit = true, so each delivery only enqueues
        // after the surrounding transaction commits (and is dropped on rollback).
        // Dispatch placement relative to the transaction is therefore safe either way.
        app(WebhookService::class)->dispatch('member.anonymised', [
            'id' => $member->id,
        ]);

        return $member->refresh();
    }
}
