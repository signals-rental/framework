<?php

namespace App\Actions\Members;

use App\Data\Members\MemberData;
use App\Data\Members\UpdateMemberData;
use App\Events\AuditableEvent;
use App\Models\Member;
use App\Services\Api\WebhookService;
use App\Services\CustomFieldValidator;
use Illuminate\Support\Facades\Gate;

class UpdateMember
{
    public function __invoke(Member $member, UpdateMemberData $data): MemberData
    {
        Gate::authorize('members.edit');

        $member->update(array_filter($data->toArray(), fn ($v) => $v !== null));

        if ($data->custom_fields !== null) {
            app(CustomFieldValidator::class)->validate('Member', $data->custom_fields);
            $member->syncCustomFields($data->custom_fields);
        }

        $member->refresh();

        event(new AuditableEvent($member, 'member.updated'));

        app(WebhookService::class)->dispatch('member.updated', [
            'member' => MemberData::fromModel($member)->toArray(),
        ]);

        return MemberData::fromModel($member);
    }
}
