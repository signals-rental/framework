<?php

namespace App\Actions\Members;

use App\Data\Members\CreateEmailData;
use App\Data\Members\EmailData;
use App\Events\AuditableEvent;
use App\Models\Member;
use Illuminate\Support\Facades\Gate;

class CreateEmail
{
    public function __invoke(Member $member, CreateEmailData $data): EmailData
    {
        Gate::authorize('members.edit');

        $email = $member->emails()->create($data->toArray());

        event(new AuditableEvent($member, 'member.email.created'));

        return EmailData::fromModel($email);
    }
}
