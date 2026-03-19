<?php

namespace App\Actions\Members;

use App\Events\AuditableEvent;
use App\Models\Member;
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

            app(\App\Services\Api\WebhookService::class)->dispatch('member.archived', [
                'id' => $member->id,
            ]);

            $member->delete();
        });
    }
}
