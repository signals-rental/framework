<?php

namespace App\Actions\Members;

use App\Events\AuditableEvent;
use App\Models\Member;
use Illuminate\Support\Facades\Gate;

class DeleteMember
{
    public function __invoke(Member $member): void
    {
        Gate::authorize('members.delete');

        event(new AuditableEvent($member, 'member.deleted'));

        app(\App\Services\Api\WebhookService::class)->dispatch('member.deleted', [
            'id' => $member->id,
        ]);

        $member->delete();
    }
}
