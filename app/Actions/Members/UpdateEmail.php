<?php

namespace App\Actions\Members;

use App\Data\Members\EmailData;
use App\Data\Members\UpdateEmailData;
use App\Events\AuditableEvent;
use App\Models\Email;
use Illuminate\Support\Facades\Gate;

class UpdateEmail
{
    public function __invoke(Email $email, UpdateEmailData $data): EmailData
    {
        Gate::authorize('members.edit');

        $email->update(array_filter($data->toArray(), fn ($v) => $v !== null));

        event(new AuditableEvent($email, 'member.email.updated'));

        return EmailData::fromModel($email->fresh());
    }
}
