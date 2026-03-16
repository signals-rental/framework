<?php

namespace App\Actions\Members;

use App\Data\Members\CreateMemberData;
use App\Data\Members\MemberData;
use App\Events\AuditableEvent;
use App\Models\Member;
use App\Services\CustomFieldValidator;
use Illuminate\Support\Facades\Gate;

class CreateMember
{
    public function __invoke(CreateMemberData $data): MemberData
    {
        Gate::authorize('members.create');

        app(CustomFieldValidator::class)->validate('Member', $data->custom_fields, enforceRequired: true);

        $member = Member::create([
            'name' => $data->name,
            'membership_type' => $data->membership_type,
            'is_active' => $data->is_active,
            'description' => $data->description,
            'locale' => $data->locale,
            'default_currency_code' => $data->default_currency_code,
            'organisation_tax_class_id' => $data->organisation_tax_class_id,
            'tag_list' => $data->tag_list,
        ]);

        $member->syncCustomFields($data->custom_fields, applyDefaults: true);

        event(new AuditableEvent($member, 'member.created'));

        app(\App\Services\Api\WebhookService::class)->dispatch('member.created', [
            'member' => MemberData::fromModel($member)->toArray(),
        ]);

        return MemberData::fromModel($member);
    }
}
