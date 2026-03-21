<?php

namespace App\Actions\Members;

use App\Events\AuditableEvent;
use App\Models\Member;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\Gate;

class DeleteMember
{
    public function __invoke(Member $member): void
    {
        Gate::authorize('members.delete');

        event(new AuditableEvent($member, 'member.deleted'));

        app(WebhookService::class)->dispatch('member.deleted', [
            'id' => $member->id,
        ]);

        $member->delete();
    }
}
