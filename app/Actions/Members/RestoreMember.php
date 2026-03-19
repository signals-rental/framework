<?php

namespace App\Actions\Members;

use App\Events\AuditableEvent;
use App\Models\Member;
use Illuminate\Support\Facades\Gate;

class RestoreMember
{
    public function __invoke(Member $member): void
    {
        Gate::authorize('members.delete');

        if (! $member->trashed()) {
            return;
        }

        $member->restore();
        $member->update(['is_active' => true]);

        event(new AuditableEvent($member, 'member.restored'));

        app(\App\Services\Api\WebhookService::class)->dispatch('member.restored', [
            'id' => $member->id,
        ]);
    }
}
