<?php

namespace App\Actions\Members;

use App\Events\AuditableEvent;
use App\Models\Member;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ArchiveMember
{
    public function __invoke(Member $member): void
    {
        Gate::authorize('members.delete');

        DB::transaction(function () use ($member): void {
            $member->update(['is_active' => false]);

            event(new AuditableEvent($member, 'member.archived'));

            $member->delete();
        });

        // Dispatch the webhook only after the transaction has committed. All queue
        // connections use after_commit: false, so dispatching inside the closure
        // would queue a delivery even if the transaction later rolled back.
        app(WebhookService::class)->dispatch('member.archived', [
            'id' => $member->id,
        ]);
    }
}
