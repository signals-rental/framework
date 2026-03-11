<?php

namespace App\Actions\Members;

use App\Data\Members\CreatePhoneData;
use App\Data\Members\PhoneData;
use App\Events\AuditableEvent;
use App\Models\Member;
use Illuminate\Support\Facades\Gate;

class CreatePhone
{
    public function __invoke(Member $member, CreatePhoneData $data): PhoneData
    {
        Gate::authorize('members.edit');

        $phone = $member->phones()->create($data->toArray());

        event(new AuditableEvent($member, 'member.phone.created'));

        return PhoneData::fromModel($phone);
    }
}
